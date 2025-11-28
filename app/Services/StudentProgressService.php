<?php

namespace App\Services;

use App\Models\StudentManage;
use App\Models\StudentGrade;
use App\Models\StudentCourse;
use App\Models\Curriculumsubject;
use App\Models\Major;
use App\Models\AcademicYear;
use Illuminate\Support\Facades\Log;

class StudentProgressService
{
    /**
     * Sync ng:
     *  - student_manage.Academic_year
     *  - student_manage.Year (FIRST YEAR, SECOND YEAR, ...)
     *  - student_course.Major_id
     *  - rebind lahat ng student_grades.subject_id sa tamang track
     *
     * @param int         $studentId
     * @param string|null $ayLabel   e.g. "2023-2024"
     * @param string|null $semester  e.g. "FIRST", "SECOND"
     */
    public static function syncFromGrades(int $studentId, ?string $ayLabel, ?string $semester): void
    {
        $student = StudentManage::find($studentId);
        if (!$student) {
            Log::warning('syncFromGrades: StudentManage not found', ['student_id' => $studentId]);
            return;
        }

        $ayLabel = trim((string) $ayLabel);
        $semNorm = strtoupper(trim((string) $semester)); // EXPECTED: FIRST / SECOND / MIDYEAR / SUMMER / SUMMER2
        $oldAY   = trim((string) $student->Academic_year);
        $oldYear = trim((string) $student->Year);

        /* ========================
         * 1) UPDATE AY + Year
         * ======================== */
        if ($ayLabel !== '') {
            $oldStart = self::extractStartYear($oldAY);
            $newStart = self::extractStartYear($ayLabel);

            // 1.1 First time AY
            if ($oldAY === '' || $oldStart === null) {
                $student->Academic_year = $ayLabel;
                $student->save();

                Log::info('syncFromGrades: set initial Academic_year for student', [
                    'student_id' => $studentId,
                    'ay'         => $ayLabel,
                ]);
            }
            // 1.2 Newer AY (e.g. 2023-2024 > 2022-2023)
            elseif ($newStart !== null && $newStart > $oldStart) {
                $student->Academic_year = $ayLabel;

                // Rule: 2 sem per AY → FIRST SEM of new AY = next YEAR
                if ($semNorm === 'FIRST') {
                    $student->Year = self::incrementYearLabel($oldYear);
                }

                $student->save();

                Log::info('syncFromGrades: bumped AY (and maybe Year)', [
                    'student_id' => $studentId,
                    'old_ay'     => $oldAY,
                    'new_ay'     => $ayLabel,
                    'old_year'   => $oldYear,
                    'new_year'   => $student->Year,
                    'semester'   => $semNorm,
                ]);
            }
        }

        /* ========================
         * 2) DETECT MAJOR via track
         *    – PRIORITY: current AY + semester
         * ======================== */

        // Hanapin kung ano yung academic_year_id ng label na ito
        $ayId = null;
        if ($ayLabel !== '') {
            $ayRow = AcademicYear::byLabel($ayLabel)->first();
            if ($ayRow) {
                $ayId = (int) $ayRow->academic_year_id;
            }
        }

        $baseQuery = StudentGrade::with('curriculumSubject')
            ->where('Student_id', $studentId);

        // Subukan muna kunin lang yung grades ng kasalukuyang AY+sem
        $grades = null;
        if ($ayId && $semNorm !== '') {
            $currentTerm = (clone $baseQuery)
                ->where('academic_year_id', $ayId)
                ->where('semester', $semNorm)
                ->get();

            if ($currentTerm->isNotEmpty()) {
                $grades = $currentTerm;

                Log::info('syncFromGrades: using CURRENT TERM grades for major detection', [
                    'student_id' => $studentId,
                    'ay_id'      => $ayId,
                    'semester'   => $semNorm,
                    'count'      => $currentTerm->count(),
                ]);
            }
        }

        // Kung wala, fallback sa lahat ng grades
        if ($grades === null) {
            $grades = $baseQuery->get();

            if ($grades->isEmpty()) {
                Log::info('syncFromGrades: no grades found; skipping major detection', [
                    'student_id' => $studentId,
                ]);
                return;
            }
        }

        $trackCandidates = [];

        foreach ($grades as $g) {
            $cs    = $g->curriculumSubject;
            $track = $cs ? trim((string) $cs->track) : '';

            if ($track !== '') {
                $trackCandidates[$track] = true;
            }
        }

        Log::info('syncFromGrades: track candidates collected', [
            'student_id' => $studentId,
            'tracks'     => array_keys($trackCandidates),
        ]);

        if (empty($trackCandidates)) {
            Log::info('syncFromGrades: no track values found on curriculum_subjects', [
                'student_id' => $studentId,
            ]);
            return;
        }

        // Try to match Major by Major_name OR Abbreviation (case-insensitive)
        $foundMajor = null;

        foreach (array_keys($trackCandidates) as $trackName) {

            // Example: "Business Analytics Track" -> "Business Analytics"
            $cleanTrack = preg_replace('/\s*track$/i', '', trim($trackName));
            $trackLower = mb_strtolower($cleanTrack);

            $m = Major::whereRaw('LOWER(Major_name) = ?', [$trackLower])
                ->orWhereRaw('LOWER(Abbreviation) = ?', [$trackLower])
                ->first();

            // Fallback: kung mukhang numeric, treat as Major_id
            if (!$m && is_numeric($trackName)) {
                $m = Major::find((int) $trackName);
            }

            if ($m) {
                $foundMajor = $m;
                Log::info('syncFromGrades: matched Major from track', [
                    'student_id'  => $studentId,
                    'track_raw'   => $trackName,
                    'track_clean' => $cleanTrack,
                    'major_id'    => $m->Major_id,
                    'major_name'  => $m->Major_name,
                    'abbr'        => $m->Abbreviation,
                ]);
                break;
            }
        }

        if (!$foundMajor) {
            Log::info('syncFromGrades: no Major matched from track list', [
                'student_id' => $studentId,
                'tracks'     => array_keys($trackCandidates),
            ]);
            return;
        }

        /* ========================
         * 3) UPDATE StudentCourse.Major_id
         * ======================== */
        StudentCourse::where('Student_id', $studentId)
            ->update(['Major_id' => $foundMajor->Major_id]);

        Log::info('syncFromGrades: updated StudentCourse.Major_id', [
            'student_id' => $studentId,
            'major_id'   => $foundMajor->Major_id,
            'major_name' => $foundMajor->Major_name,
        ]);

        /* ========================
         * 4) REBIND subject_id to track
         * ======================== */
        self::rebindSubjectsToMajorTrack($studentId, $foundMajor->Major_id);
    }

    /**
     * I-increment ang Year label:
     * FIRST YEAR → SECOND YEAR → THIRD YEAR → FOURTH YEAR → FOURTH YEAR (cap)
     */
    private static function incrementYearLabel(?string $current): string
    {
        $map = [
            'FIRST YEAR'  => 'SECOND YEAR',
            'SECOND YEAR' => 'THIRD YEAR',
            'THIRD YEAR'  => 'FOURTH YEAR',
            'FOURTH YEAR' => 'FOURTH YEAR',
        ];

        $normalized = strtoupper(trim((string) $current));

        return $map[$normalized] ?? 'FIRST YEAR'; // default kung wala pang laman
    }

    /**
     * Extract first 4-digit start year from "2023-2024".
     * Return null kung di ma-parse.
     */
    private static function extractStartYear(?string $label): ?int
    {
        $label = trim((string) $label);
        if ($label === '') return null;

        if (preg_match('/(\d{4})/', $label, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * Kapag sure na tayo sa Major ng student, lahat ng StudentGrade niya
     * ire-relate natin sa curriculum_subjects ng tamang TRACK (major).
     *
     * Rule:
     *  - Gamitin yung StudentCourse.curriculum_id ng student
     *  - Gamitin yung Major_name at/o Abbreviation bilang value ng curriculum_subjects.track
     *  - Match by course_code (ignoring spaces, case-insensitive)
     */
    private static function rebindSubjectsToMajorTrack(int $studentId, int $majorId): void
    {
        $major = Major::find($majorId);
        if (!$major) {
            Log::warning('rebindSubjectsToMajorTrack: Major not found', [
                'student_id' => $studentId,
                'major_id'   => $majorId,
            ]);
            return;
        }

        $trackName = trim((string) $major->Major_name);   // e.g. "Business Analytics"
        $abbr      = trim((string) $major->Abbreviation); // e.g. "BA"

        // Build possible values for curriculum_subjects.track
        $trackOptions = [];

        if ($trackName !== '') {
            $trackOptions[] = $trackName;                // "Business Analytics"
            $trackOptions[] = $trackName . ' Track';     // "Business Analytics Track"
        }
        if ($abbr !== '') {
            $trackOptions[] = $abbr;                     // "BA"
            $trackOptions[] = $abbr . ' Track';          // "BA Track"
        }

        // unique + remove empty
        $trackOptions = array_values(array_unique(array_filter($trackOptions)));

        Log::info('rebindSubjectsToMajorTrack: using track options', [
            'student_id'    => $studentId,
            'major_id'      => $majorId,
            'major_name'    => $trackName,
            'abbr'          => $abbr,
            'track_options' => $trackOptions,
        ]);

        $course = StudentCourse::where('Student_id', $studentId)->first();
        if (!$course) {
            Log::warning('rebindSubjectsToMajorTrack: StudentCourse not found', [
                'student_id' => $studentId,
            ]);
            return;
        }

        $curriculumId = (int) $course->curriculum_id;

        // Lahat ng subjects sa curriculum na ’to + specific track(s)
        $currSubjects = Curriculumsubject::where('curriculum_id', $curriculumId)
            ->when(!empty($trackOptions), function ($q) use ($trackOptions) {
                $q->whereIn('track', $trackOptions);
            })
            ->get();

        if ($currSubjects->isEmpty()) {
            Log::warning('rebindSubjectsToMajorTrack: No curriculum_subjects for track options', [
                'student_id'    => $studentId,
                'major_id'      => $majorId,
                'track_options' => $trackOptions,
                'curriculum_id' => $curriculumId,
            ]);
            return;
        }

        // Build map: normalized code -> subject_id
        $subjectsByCode = [];
        foreach ($currSubjects as $cs) {
            /** @var Curriculumsubject $cs */
            $codeRaw = (string) $cs->Code; // adjust kung iba column name mo
            $codeKey = strtoupper(preg_replace('/\s+/', '', $codeRaw)); // "BAT 401" → "BAT401"
            $subjectsByCode[$codeKey] = (int) $cs->subject_id;
        }

        $grades       = StudentGrade::where('Student_id', $studentId)->get();
        $updatedCount = 0;

        foreach ($grades as $g) {
            /** @var StudentGrade $g */
            $codeRaw = (string) $g->course_code;
            $codeKey = strtoupper(preg_replace('/\s+/', '', $codeRaw));

            if (!isset($subjectsByCode[$codeKey])) {
                Log::debug('rebindSubjectsToMajorTrack: no curriculum_subject match for code', [
                    'student_id'  => $studentId,
                    'course_code' => $codeRaw,
                    'track_opts'  => $trackOptions,
                ]);
                continue;
            }

            $newSubjectId = $subjectsByCode[$codeKey];

            if ((int) $g->subject_id === $newSubjectId) {
                continue;
            }

            $oldSubjectId  = $g->subject_id;
            $g->subject_id = $newSubjectId;
            $g->save();
            $updatedCount++;

            Log::info('Rebound subject_id to track major', [
                'student_id'      => $studentId,
                'course_code'     => $codeRaw,
                'old_subject_id'  => $oldSubjectId,
                'new_subject_id'  => $newSubjectId,
                'major_id'        => $majorId,
                'major_name'      => $major->Major_name,
                'abbr'            => $major->Abbreviation,
                'curriculum_id'   => $curriculumId,
            ]);
        }

        Log::info('rebindSubjectsToMajorTrack finished', [
            'student_id'    => $studentId,
            'major_id'      => $majorId,
            'track_options' => $trackOptions,
            'updated_count' => $updatedCount,
        ]);
    }
}
