<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\GraduationRequirement;
use App\Models\GraduationForm;
use App\Models\StudentManage;
use App\Models\StudentCourse;
use App\Models\StudentGrade;
use App\Models\Curriculumsubject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GraduationRequirementController extends Controller
{
    public function store(Request $request)
    {
        // --- Validate common fields ---
        $baseRules = [
            'approval_sheet'      => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'library_certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'barangay_clearance'  => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'birth_certificate'   => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'approval_to_follow'  => 'nullable|in:0,1',
            'library_to_follow'   => 'nullable|in:0,1',
        ];
        $request->validate($baseRules);

        // --- applicationform_grad: pwedeng file o string path ---
        if ($request->hasFile('applicationform_grad')) {
            $request->validate([
                'applicationform_grad' => 'required|file|mimes:pdf|max:20480',
            ]);
        } else {
            $request->validate([
                'applicationform_grad' => 'required|string|max:1024',
            ]);
        }

        try {
            /* ============================================================
             * 1) Resolve current GraduationForm_id via logged-in student
             * ==========================================================*/
            $loginId = optional($request->user())->Login_id
                ?? session('login_id')
                ?? session('Login_id');

            $student = $loginId
                ? StudentManage::where('Login_id', $loginId)->first()
                : null;

            $gradFormId = null;

            if ($student) {
                $gradForm = GraduationForm::where('Student_id', $student->Student_id)
                    ->orderByDesc('GraduationForm_id')
                    ->first();

                if ($gradForm) {
                    $gradFormId = $gradForm->GraduationForm_id;
                }
            }

            if (!$gradFormId) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'No graduation application found. Please generate the application form first.',
                ], 422);
            }

            /* ============================================================
             * 2) Re-evaluate graduation status instead of using existing
             * ==========================================================*/
            $graduationRemarks   = 'NOT GRADUATING'; // Default
            $existingRequirement = GraduationRequirement::where('GraduationForm_id', $gradFormId)->first();

            try {
                // Get the latest COR evaluation
                $corTextPath = storage_path('graduation/cor/cor_output.txt');

                if ($student && file_exists($corTextPath)) {
                    $corText = file_get_contents($corTextPath);

                    // Use the same extraction and evaluation logic
                    $corCourses = $this->extractCoursesFromCorText($corText);
                    $evaluation = $this->evaluateGraduationStatus($student, $corCourses);

                    // Use the simplified graduation status
                    $graduationRemarks =
                        ($evaluation['graduation_status'] === 'GRADUATED' ||
                         $evaluation['graduation_status'] === 'CANDIDATE FOR GRADUATION' ||
                         $evaluation['graduation_status'] === 'GRADUATING')
                            ? 'GRADUATING'
                            : 'NOT GRADUATING';

                    Log::info('Re-evaluated graduation status during requirements submission', [
                        'student_id'      => $student->Student_id,
                        'original_status' => $evaluation['graduation_status'],
                        'final_remarks'   => $graduationRemarks,
                    ]);
                } else {
                    // Fallback to existing remarks if no COR found or no student
                    $graduationRemarks = $existingRequirement->remarks ?? 'NOT GRADUATING';
                    Log::warning('No COR file found for re-evaluation, using existing remarks', [
                        'student_id' => $student->Student_id ?? null,
                        'remarks'    => $graduationRemarks,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Graduation re-evaluation failed in requirements submission: ' . $e->getMessage(), [
                    'student_id' => $student->Student_id ?? null,
                ]);
                // Fallback to existing remarks if re-evaluation fails
                $graduationRemarks = $existingRequirement->remarks ?? 'NOT GRADUATING';
            }

            /* ============================================================
             * 3) Store optional uploads
             * ==========================================================*/
            $approvalPath = $request->file('approval_sheet')
                ? $request->file('approval_sheet')->store('public/graduation/requirements/approval')
                : null;

            $libraryPath = $request->file('library_certificate')
                ? $request->file('library_certificate')->store('public/graduation/requirements/library')
                : null;

            $brgyPath = $request->file('barangay_clearance')
                ? $request->file('barangay_clearance')->store('public/graduation/requirements/brgy')
                : null;

            $birthPath = $request->file('birth_certificate')
                ? $request->file('birth_certificate')->store('public/graduation/requirements/birth')
                : null;

            /* ============================================================
             * 4) Handle generated application PDF (file OR string)
             * ==========================================================*/
            if ($request->hasFile('applicationform_grad')) {
                $stored = $request->file('applicationform_grad')
                    ->store('public/graduation/application_forms');

                $appFormValue = Storage::url($stored);   // /storage/...
            } else {
                $raw = trim((string) $request->input('applicationform_grad'));

                if (str_starts_with($raw, 'public/')) {
                    $appFormValue = Storage::url($raw);
                } else {
                    $appFormValue = $raw;
                }
            }

            /* ============================================================
             * 5) Build payload & upsert (same GraduationForm_id)
             * ==========================================================*/

            // Decide status:
            // - First submission: "For Evaluation"
            // - May existing record: i-keep yung current status (e.g. Evaluated/Returned/etc.)
            $statusValue = $existingRequirement->status ?? 'For Evaluation';

            $payload = [
                'GraduationForm_id'    => $gradFormId,
                'Approval_Sheet'       => $approvalPath,
                'Certificate_Library'  => $libraryPath,
                'Barangay_Clearance'   => $brgyPath,
                'Birth_Certificate'    => $birthPath,
                'applicationform_grad' => $appFormValue,
                'remarks'              => $graduationRemarks, // GRADUATING / NOT GRADUATING
                'status'               => $statusValue,       // 🔹 bagong field
            ];

            // Add approval_to_follow and library_to_follow if they exist in request
            if ($request->has('approval_to_follow')) {
                $payload['approval_to_follow'] = $request->input('approval_to_follow');
            }

            if ($request->has('library_to_follow')) {
                $payload['library_to_follow'] = $request->input('library_to_follow');
            }

            // Update or create the graduation requirement
            $rec = GraduationRequirement::withoutTimestamps(function () use ($gradFormId, $payload) {
                return GraduationRequirement::updateOrCreate(
                    ['GraduationForm_id' => $gradFormId],
                    $payload
                );
            });

            Log::info('Graduation requirements saved successfully', [
                'graduation_form_id' => $gradFormId,
                'student_id'         => $student->Student_id ?? null,
                'remarks'            => $graduationRemarks,
                'status'             => $payload['status'],
            ]);

            return response()->json([
                'ok'  => true,
                'id'  => $rec->GraduationReq_id,
                'row' => $payload,
            ]);

        } catch (ValidationException $ve) {
            throw $ve;
        } catch (\Throwable $e) {
            Log::error('GraduationRequirement store failed', [
                'err'   => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'ok'      => false,
                'message' => 'Server error while saving requirements.',
            ], 500);
        }
    }

    /**
     * Extract course codes from COR text - IMPROVED VERSION
     */
    private function extractCoursesFromCorText(string $corText): array
    {
        $courses = [];

        // Split text into lines
        $lines = explode("\n", $corText);

        $inCoursesSection = false;

        foreach ($lines as $line) {
            $line = trim($line);

            // Look for the start of courses section
            if (str_contains($line, 'COURSE CODE') || str_contains($line, 'COURSE TITLE')) {
                $inCoursesSection = true;
                continue;
            }

            // Look for the end of courses section
            if ($inCoursesSection && (str_contains($line, 'Scholarship/s:') || str_contains($line, 'ASSESSMENT') || str_contains($line, 'Tuition Fee'))) {
                $inCoursesSection = false;
                break;
            }

            // If we're in the courses section, extract course codes
            if ($inCoursesSection && $line !== '') {
                // Try multiple patterns to catch all course formats

                // Pattern 1: "BAT 405Analytics Application" - space between code and number, no space before title
                if (preg_match('/^([A-Z]{2,4})\s+(\d{3})([A-Za-z].*)$/', $line, $matches)) {
                    $code = $matches[1] . ' ' . $matches[2];
                    $courses[] = [
                        'code'          => $code,
                        'original_line' => $line,
                    ];
                    Log::info('Found course (Pattern 1)', ['code' => $code, 'line' => $line]);
                    continue;
                }

                // Pattern 2: "IT 411 Capstone Project 2" - space between code and number, space before title
                if (preg_match('/^([A-Z]{2,4})\s+(\d{3})\s+([A-Za-z].*)$/', $line, $matches)) {
                    $code = $matches[1] . ' ' . $matches[2];
                    $courses[] = [
                        'code'          => $code,
                        'original_line' => $line,
                    ];
                    Log::info('Found course (Pattern 2)', ['code' => $code, 'line' => $line]);
                    continue;
                }

                // Pattern 3: "BAT405Analytics Application" - no spaces at all
                if (preg_match('/^([A-Z]{2,4})(\d{3})([A-Za-z].*)$/', $line, $matches)) {
                    $code = $matches[1] . ' ' . $matches[2];
                    $courses[] = [
                        'code'          => $code,
                        'original_line' => $line,
                    ];
                    Log::info('Found course (Pattern 3)', ['code' => $code, 'line' => $line]);
                    continue;
                }

                // Pattern 4: Just look for any combination of 2-4 letters followed by 3 digits
                if (preg_match('/([A-Z]{2,4}\s?\d{3})/', $line, $matches)) {
                    $rawCode = $matches[1];
                    // Normalize the code
                    $code = preg_replace('/([A-Z]{2,4})\s?(\d{3})/', '$1 $2', $rawCode);
                    $courses[] = [
                        'code'          => $code,
                        'original_line' => $line,
                    ];
                    Log::info('Found course (Pattern 4)', [
                        'code' => $code,
                        'raw'  => $rawCode,
                        'line' => $line,
                    ]);
                }
            }
        }

        // Remove duplicates
        $uniqueCourses = [];
        foreach ($courses as $course) {
            $uniqueCourses[$course['code']] = $course;
        }

        Log::info('COR courses extraction result', [
            'total_courses_found' => count($uniqueCourses),
            'courses_found'       => array_keys($uniqueCourses),
        ]);

        return array_values($uniqueCourses);
    }

    /**
     * Evaluate graduation status based on curriculum requirements and completed courses
     */
    private function evaluateGraduationStatus(StudentManage $student, array $corCourses): array
    {
        try {
            // Get student's curriculum ID
            $curriculumId = $student->curriculum_id;
            if (!$curriculumId) {
                return [
                    'graduation_status'        => 'NOT GRADUATING',
                    'remarks'                  => 'NOT GRADUATING',
                    'matched_courses'          => [],
                    'missing_courses'          => [],
                    'total_curriculum_courses' => 0,
                    'total_matched'            => 0,
                    'total_missing'            => 0,
                ];
            }

            // Get student's track (Major) from StudentCourse
            $studentCourse = StudentCourse::with(['major'])
                ->where('Student_id', $student->Student_id)
                ->orderByDesc('StudentCourse_id')
                ->first();

            $studentTrack = null;
            if ($studentCourse && $studentCourse->major) {
                $studentTrack = $studentCourse->major->Major_name;
            }

            // Get all courses student has taken from StudentGrade (historical grades)
            $studentGrades = StudentGrade::where('Student_id', $student->Student_id)
                ->select('course_code', 'grade', 'subject_id')
                ->get();

            // Get all courses from student's curriculum
            $curriculumCourses = Curriculumsubject::where('curriculum_id', $curriculumId)
                ->select('subject_id', 'Code', 'Course_Title', 'year_level', 'semester', 'track', 'units')
                ->orderBy('year_level')
                ->orderBy('semester')
                ->get();

            if ($curriculumCourses->isEmpty()) {
                return [
                    'graduation_status'        => 'NOT GRADUATING',
                    'remarks'                  => 'NOT GRADUATING',
                    'matched_courses'          => [],
                    'missing_courses'          => [],
                    'total_curriculum_courses' => 0,
                    'total_matched'            => 0,
                    'total_missing'            => 0,
                ];
            }

            // Filter courses based on student's track
            $requiredCourses = $curriculumCourses->filter(function ($course) use ($studentTrack) {
                if (empty($course->track) || $course->track === '') {
                    return true;
                }
                if (empty($studentTrack)) {
                    return empty($course->track);
                }
                return empty($course->track) ||
                    str_contains($course->track, $studentTrack) ||
                    str_contains($studentTrack, $course->track);
            });

            // Create combined list of all courses student has taken
            $allTakenCourses = [];

            // 1. Add courses from StudentGrade (completed courses)
            foreach ($studentGrades as $grade) {
                $normalizedCode = $this->normalizeCourseCode($grade->course_code);
                $allTakenCourses[$normalizedCode] = [
                    'source'        => 'GRADE_HISTORY',
                    'grade'         => $grade->grade,
                    'original_code' => $grade->course_code,
                ];
            }

            // 2. Add courses from COR (currently enrolled)
            foreach ($corCourses as $corCourse) {
                $normalizedCode = $this->normalizeCourseCode($corCourse['code']);
                $allTakenCourses[$normalizedCode] = [
                    'source'        => 'COR',
                    'grade'         => 'CURRENTLY ENROLLED',
                    'original_code' => $corCourse['code'],
                ];
            }

            // Match curriculum courses with taken courses
            $matchedCourses = [];
            $missingCourses = [];

            foreach ($requiredCourses as $course) {
                $normalizedCode = $this->normalizeCourseCode($course->Code);

                if (isset($allTakenCourses[$normalizedCode])) {
                    // Course is taken (either completed or currently enrolled)
                    $takenInfo = $allTakenCourses[$normalizedCode];
                    $matchedCourses[] = [
                        'code'       => $course->Code,
                        'title'      => $course->Course_Title,
                        'year_level' => $course->year_level,
                        'semester'   => $course->semester,
                        'track'      => $course->track,
                        'units'      => $course->units,
                        'status'     => 'COMPLETED',
                        'source'     => $takenInfo['source'],
                        'grade'      => $takenInfo['grade'],
                    ];
                } else {
                    // Course is missing
                    $missingCourses[] = [
                        'code'       => $course->Code,
                        'title'      => $course->Course_Title,
                        'year_level' => $course->year_level,
                        'semester'   => $course->semester,
                        'track'      => $course->track,
                        'units'      => $course->units,
                        'status'     => 'MISSING',
                    ];
                }
            }

            // Determine graduation status
            $graduationStatus      = 'NOT GRADUATING';
            $remarks               = 'NOT GRADUATING';
            $canApplyForGraduation = false;
            $canApplyForLatin      = false;

            if (empty($missingCourses)) {
                // All courses completed - ready to graduate
                $graduationStatus      = 'GRADUATING';
                $remarks               = 'GRADUATING';
                $canApplyForGraduation = true;
                $canApplyForLatin      = true;
            } else {
                // Check if student is candidate for graduation
                $missingFourthYearSecond = array_filter($missingCourses, function ($course) {
                    return str_contains($course['year_level'], 'FOURTH') &&
                        str_contains($course['semester'], 'SECOND');
                });

                $missingOther = array_filter($missingCourses, function ($course) {
                    return !(
                        str_contains($course['year_level'], 'FOURTH') &&
                        str_contains($course['semester'], 'SECOND')
                    );
                });

                // If only missing FOURTH YEAR, SECOND SEMESTER courses (like internship)
                if (empty($missingOther) && count($missingFourthYearSecond) > 0) {
                    $graduationStatus      = 'GRADUATING';
                    $remarks               = 'GRADUATING';
                    $canApplyForGraduation = true;
                    $canApplyForLatin      = false;
                } else {
                    // Missing courses from previous years/semesters
                    $graduationStatus      = 'NOT GRADUATING';
                    $remarks               = 'NOT GRADUATING';
                    $canApplyForGraduation = false;
                    $canApplyForLatin      = false;
                }
            }

            Log::info('Graduation evaluation result', [
                'student_id'              => $student->Student_id,
                'graduation_status'       => $graduationStatus,
                'missing_count'           => count($missingCourses),
                'missing_courses'         => array_column($missingCourses, 'code'),
                'can_apply_for_graduation'=> $canApplyForGraduation,
                'can_apply_for_latin'     => $canApplyForLatin,
            ]);

            return [
                'graduation_status'        => $graduationStatus,
                'remarks'                  => $remarks,
                'matched_courses'          => $matchedCourses,
                'missing_courses'          => $missingCourses,
                'total_curriculum_courses' => $requiredCourses->count(),
                'total_matched'            => count($matchedCourses),
                'total_missing'            => count($missingCourses),
                'completion_percentage'    => $requiredCourses->count() > 0
                    ? round((count($matchedCourses) / $requiredCourses->count()) * 100, 2)
                    : 0,
                'student_track'            => $studentTrack,
                'can_apply_for_graduation' => $canApplyForGraduation,
                'can_apply_for_latin'      => $canApplyForLatin,
                'cor_courses_count'        => count($corCourses),
                'grade_courses_count'      => $studentGrades->count(),
                'combined_courses_count'   => count($allTakenCourses),
            ];

        } catch (\Exception $e) {
            Log::error('Graduation evaluation error', [
                'student_id' => $student->Student_id ?? null,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            return [
                'graduation_status'        => 'NOT GRADUATING',
                'remarks'                  => 'NOT GRADUATING',
                'matched_courses'          => [],
                'missing_courses'          => [],
                'total_curriculum_courses' => 0,
                'total_matched'            => 0,
                'total_missing'            => 0,
                'can_apply_for_graduation' => false,
                'can_apply_for_latin'      => false,
            ];
        }
    }

    /**
     * Normalize course code for consistent matching
     */
    private function normalizeCourseCode(string $courseCode): string
    {
        if (empty($courseCode)) {
            return '';
        }

        // Remove all spaces, dashes, and convert to uppercase
        $normalized = strtoupper(preg_replace('/[\s\-]+/', '', trim($courseCode)));

        return $normalized;
    }
}
