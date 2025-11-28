<?php

namespace App\Http\Controllers\Programchair;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Application;
use App\Models\Program;
use App\Models\College;
use App\Models\Post;
use App\Models\UserDesignation;
use App\Models\Designation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class DeansHonorListReportController extends Controller
{
    /**
     * Return available AY / Semester options based on posts for this program.
     * Used by the JS dropdown before download.
     */
    public function options(int $programId)
    {
        // All posts created by program chair(s) of this program
        $posts = Post::with('userDesignation')
            ->whereHas('userDesignation', function ($q) use ($programId) {
                $q->where('Program_id', $programId);
            })
            ->orderByDesc('Start_date')
            ->orderByDesc('Post_id')
            ->get(['Academic_year', 'Semester', 'Start_date', 'Post_id']);

        $years = $posts->pluck('Academic_year')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $sems = $posts->pluck('Semester')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $latest = $posts->first();

        // ----- fallbacks if walang post -----
        if (empty($years)) {
            $now = now();
            $y   = $now->year;
            $m   = $now->month;
            $years[] = ($m >= 8)
                ? "{$y}-" . ($y + 1)
                : ($y - 1) . "-{$y}";
        }

        if (empty($sems)) {
            $now = now();
            $m   = $now->month;
            if ($m >= 8 && $m <= 12) {
                $sems[] = 'First Semester';
            } elseif ($m >= 1 && $m <= 5) {
                $sems[] = 'Second Semester';
            } else {
                $sems[] = 'Midyear Term';
            }
        }

        return response()->json([
            'academic_years'   => $years,
            'semesters'        => $sems,
            'default_ay'       => $latest->Academic_year ?? $years[0] ?? null,
            'default_semester' => $latest->Semester      ?? $sems[0]  ?? null,
        ]);
    }

    /**
     * Generate Dean's Honor List PDF for a program.
     */
    public function download(Request $request, int $programId)
    {
        $term = trim((string) $request->query('term', '')); // e.g. "Second Semester"
        $ay   = trim((string) $request->query('ay', ''));   // e.g. "2024-2025"

        $program = Program::findOrFail($programId);
        $college = College::find($program->College_id);

        // -------------------------------------------------
        // 1. Get all APPROVED applications for this program
        // -------------------------------------------------
        $approvedStatuses = [
            'Approved',
            // 'Approved by Dean',
            // 'Dean Approved',
        ];

        $apps = Application::with([
                // From StudentCourse → Program
                'student.studentCourse.program' => function ($q) {
                    $q->select('Program_id', 'Program_name', 'Abbreviation');
                },
                // Fallback from curriculumAy → Program
                'student.curriculum.curriculumAy.program' => function ($q) {
                    $q->select('Program_id', 'Program_name', 'Abbreviation');
                },
            ])
            ->withStatusIn($approvedStatuses)
            // Program filter via student_course.Program_id (primary)
            // with fallback to curriculumAy.Program_id
            ->whereHas('student', function ($q) use ($programId) {
                $q->whereHas('studentCourse', function ($qq) use ($programId) {
                    $qq->where('Program_id', $programId);
                })->orWhereHas('curriculum.curriculumAy', function ($qq) use ($programId) {
                    $qq->where('Program_id', $programId);
                });
            })
            ->get();

        // -------------------------------------------------
        // 2. Build rows (sorted by GWA, then name)
        // -------------------------------------------------
        $rows = $apps->map(function ($a) {
                $s = $a->student;
                if (!$s) {
                    return null; // safety guard if orphaned
                }

                // Full name
                $first  = (string) ($s->First_name ?? '');
                $middle = (string) ($s->Middle_name ?? '');
                $last   = (string) ($s->Last_name ?? '');
                $title  = (string) ($s->Title ?? '');
                $mi     = $middle !== '' ? (mb_strtoupper(mb_substr($middle, 0, 1)) . '.') : '';

                $pretty = fn ($v) => mb_convert_case(trim((string) $v), MB_CASE_TITLE, 'UTF-8');

                $fullname = trim(
                    ($title ? $title . ' ' : '') .
                    $pretty($first) . ' ' .
                    ($mi ? $mi . ' ' : '') .
                    $pretty($last)
                );

                // GWA numeric for sorting
                $gwaNum = is_numeric($a->GWA) ? (float) $a->GWA : INF;

                // -------- Program label (fix for collection) --------
                $progFromCourse = null;

                if ($s->relationLoaded('studentCourse')) {
                    $rel = $s->studentCourse;

                    // If relation is hasMany (Collection)
                    if ($rel instanceof \Illuminate\Support\Collection) {
                        $progFromCourse = $rel->first()?->program;
                    } else {
                        // If relation is hasOne / belongsTo
                        $progFromCourse = $rel?->program;
                    }
                }

                // Fallback from curriculumAy
                $progFromCurr = $s->curriculum?->curriculumAy?->program;

                $prog = $progFromCourse ?? $progFromCurr;

                $programLabel = strtoupper((string) (
                    $prog->Abbreviation
                    ?? $prog->Program_name
                    ?? ''
                ));

                // Year level
                $yearLabel = $this->resolveStudentYear($s) ?? '—';

                return [
                    '_sort_gwa' => $gwaNum,
                    'no'        => 0, // set after sort
                    'name'      => $fullname,
                    'program'   => $programLabel ?: '—',
                    'year'      => $yearLabel,
                    'gwa'       => is_finite($gwaNum) ? number_format($gwaNum, 4) : '—',
                    'rank'      => $this->rankLabel(is_finite($gwaNum) ? $gwaNum : 10),
                ];
            })
            ->filter() // remove nulls
            ->sortBy([['_sort_gwa', 'asc'], ['name', 'asc']])
            ->values()
            ->map(function ($r, $i) {
                $r['no'] = $i + 1;
                unset($r['_sort_gwa']);
                return $r;
            })
            ->all();

        // -------------------------------------------------
        // 3. Header (from dropdown / fallback)
        // -------------------------------------------------
        if ($term === '') {
            $term = 'Second Semester';
        }

        if ($ay === '') {
            $year = now()->year;
            $ay   = $year . '-' . ($year + 1);
        }

        $header = [
            'title'      => "DEAN’S HONORS LIST",
            'department' => 'Information Technology Education Programs Department',
            'term'       => mb_strtoupper($term),
            'ay'         => $ay,
            'university' => 'BATANGAS STATE UNIVERSITY',
            'campus'     => 'ARASOF - Nasugbu Campus',
            'college'    => (string) ($college->College_name ?? 'College of Informatics and Computing Sciences'),
        ];

        // -------------------------------------------------
        // 4. Footer: Program Chair + Dean (same as before)
        // -------------------------------------------------
        $loginUser     = auth()->user();
        $pcDesignation = $loginUser?->userDesignation;

        $preparedName  = 'Asst. Prof. BENJIE R. SAMONTE';
        $preparedTitle = 'Department Chairperson, ITE Programs';

        if ($pcDesignation && $pcDesignation->user) {
            $u = $pcDesignation->user;
            $fn    = (string) ($u->First_name ?? '');
            $mn    = (string) ($u->Middle_name ?? '');
            $ln    = (string) ($u->Last_name ?? '');
            $title = (string) ($u->Title ?? '');
            $mi    = $mn ? (mb_substr($mn, 0, 1) . '.') : '';
            $pretty = fn ($v) => mb_convert_case(trim((string) $v), MB_CASE_TITLE, 'UTF-8');

            $preparedName = trim(
                ($title ? $title . ' ' : '') .
                $pretty($fn) . ' ' .
                ($mi ? $mi . ' ' : '') .
                $pretty($ln)
            );

            $preparedTitle = $pcDesignation->designation->Designation_name
                ?? 'Department Chairperson, ITE Programs';
        }

        $deanDesignationIds = Designation::query()
            ->where('Access', 'Dean')
            ->orWhere('Designation_name', 'LIKE', '%Dean%')
            ->pluck('Designation_id');

        $deanUD = UserDesignation::with(['user', 'designation'])
            ->whereIn('Designation_id', $deanDesignationIds)
            ->where('College_id', $program->College_id)
            ->first();

        $collegeShort = $college->Abbreviation
            ?? $college->College_shortname
            ?? $college->College_name
            ?? 'CICS';

        $certName  = 'Dean';
        $certTitle = 'Dean, ' . $collegeShort;

        if ($deanUD && $deanUD->user) {
            $u = $deanUD->user;
            $fn    = (string) ($u->First_name ?? '');
            $mn    = (string) ($u->Middle_name ?? '');
            $ln    = (string) ($u->Last_name ?? '');
            $title = (string) ($u->Title ?? '');
            $mi    = $mn ? (mb_substr($mn, 0, 1) . '.') : '';
            $pretty = fn ($v) => mb_convert_case(trim((string) $v), MB_CASE_TITLE, 'UTF-8');

            $certName = trim(
                ($title ? $title . ' ' : '') .
                $pretty($fn) . ' ' .
                ($mi ? $mi . ' ' : '') .
                $pretty($ln)
            );

            $desigName = $deanUD->designation->Designation_name ?? 'Dean';

            if (stripos($desigName, $collegeShort) === false) {
                $certTitle = $desigName . ', ' . $collegeShort;
            } else {
                $certTitle = $desigName;
            }
        }

        $footer = [
            'prepared_name'   => $preparedName,
            'prepared_title'  => $preparedTitle,
            'prepared_date'   => now()->format('m/d/Y'),
            'certified_name'  => $certName,
            'certified_title' => $certTitle,
        ];

        $pdf = Pdf::loadView('reports.deans_honor_list', [
                'header'   => $header,
                'footer'   => $footer,
                'rows'     => $rows,
                'program'  => $program,
                'college'  => $college,
                'students' => $apps,
                'term'     => $header['term'],
                'ay'       => $header['ay'],
            ])
            ->setPaper('A4', 'portrait');

        $programName = strtoupper($program->Program_name ?? 'PROGRAM');
        $safeProg    = Str::slug($programName, '_');
        $safeTerm    = Str::slug($header['term'], '_');
        $safeAy      = Str::slug($header['ay'], '_');
        $filename    = "Deans_Honors_List_{$safeProg}_{$safeTerm}_{$safeAy}.pdf";

        return $pdf->download($filename);
    }


    // -------------------------------------------------
    // Rank label helper
    // -------------------------------------------------
    private function rankLabel(float $gwa): string
    {
        if ($gwa >= 1.0000 && $gwa <= 1.2500) return 'Tech Savant';
        if ($gwa <= 1.5000)                    return 'Tech Virtuoso';
        if ($gwa <= 1.7500)                    return 'Tech Prodigy';
        return '—';
    }

    // -------------------------------------------------
    // Year-level helpers
    // -------------------------------------------------
    private function resolveStudentYear($student): ?string
    {
        $candidates = [
            $student->Year_level ?? null,
            $student->YearLevel ?? null,
            $student->Year ?? null,
            $student->year_level ?? null,
            $student->Section ?? null,
            $student->section ?? null,
        ];

        foreach ($candidates as $v) {
            $norm = $this->normalizeYearLevel($v);
            if ($norm) return $norm;
        }

        return null;
    }

    private function normalizeYearLevel($value): ?string
    {
        if ($value === null || $value === '') return null;

        // numeric → label
        if (is_numeric($value)) {
            $map = [
                1 => 'FIRST YEAR',
                2 => 'SECOND YEAR',
                3 => 'THIRD YEAR',
                4 => 'FOURTH YEAR',
            ];
            $n = (int) $value;
            return $map[$n] ?? strtoupper((string) $value);
        }

        // extract number from "BSIT 3-1", "3A", etc.
        if (preg_match('/\b([1-4])\b/', (string) $value, $m)) {
            return $this->normalizeYearLevel((int) $m[1]);
        }

        return strtoupper((string) $value);
    }
}
