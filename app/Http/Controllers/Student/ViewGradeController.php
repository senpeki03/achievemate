<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\StudentGrade;
use App\Models\AcademicYear;
use App\Models\Grades;
use App\Models\Login;
use App\Models\StudentManage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\StudentCourse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ViewGradeController extends Controller
{
    /** UI page (icons + modals) */
    public function index(Request $request)
    {
        return view('student.viewgrade');
    }

    /** Get the logged-in student's username (email) */
    private function getStudentUsername()
    {
        $studentId = $this->getStudentId();
        if (!$studentId) {
            return null;
        }

        // Get student record with login relation
        $student = StudentManage::with('login')->find($studentId);
        
        if ($student && $student->login) {
            return $student->login->username;
        }

        return null;
    }

    /** Send verification code automatically when opening View All Grades */
    public function sendAutoVerificationCode(Request $request)
    {
        try {
            // Get the username from the logged-in student
            $username = $this->getStudentUsername();

            if (!$username) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student record or username not found.'
                ], 404);
            }

            // Generate 6-digit code
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            // Store code in session with expiration (5 minutes)
            session([
                'verification_code' => $code,
                'verification_username' => $username,
                'verification_expires' => now()->addMinutes(5)
            ]);

            // Send email to the username (which is the email address)
            $this->sendVerificationEmail($username, $code, $username);

            Log::info('Auto verification code sent', [
                'username' => $username,
                'code' => $code
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Verification code sent to your email.',
                'username' => $username,
                'code' => $code // Remove this in production - only for testing
            ]);

        } catch (\Exception $e) {
            Log::error('Auto send verification code error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification code. Please try again.'
            ], 500);
        }
    }

    /** Resend verification code */
    public function resendVerificationCode(Request $request)
    {
        try {
            // Get the username from session or from student record
            $username = session('verification_username') ?? $this->getStudentUsername();

            if (!$username) {
                return response()->json([
                    'success' => false,
                    'message' => 'Username not found.'
                ], 400);
            }

            // Check if we can resend (prevent spam)
            $lastSent = session('verification_last_sent');
            if ($lastSent && now()->diffInSeconds($lastSent) < 30) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please wait 30 seconds before requesting a new code.'
                ], 429);
            }

            // Generate new 6-digit code
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            // Update session with new code and expiration
            session([
                'verification_code' => $code,
                'verification_username' => $username,
                'verification_expires' => now()->addMinutes(5),
                'verification_last_sent' => now()
            ]);

            // Send new verification email to the username (email)
            $this->sendVerificationEmail($username, $code, $username);

            Log::info('Verification code resent', [
                'username' => $username,
                'code' => $code
            ]);

            return response()->json([
                'success' => true,
                'message' => 'New verification code sent to your email.',
                'code' => $code // Remove this in production - only for testing
            ]);

        } catch (\Exception $e) {
            Log::error('Resend verification code error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to resend verification code. Please try again.'
            ], 500);
        }
    }

    /** Verify the entered code */
    public function verifyCode(Request $request)
    {
        try {
            $request->validate([
                'code' => 'required|string|size:6'
            ]);

            $enteredCode = $request->code;
            $username = session('verification_username');

            // Check if verification session exists
            if (!$username) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please request a verification code first.'
                ], 400);
            }

            // Check expiration
            if (now()->gt(session('verification_expires'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Verification code has expired. Please request a new one.'
                ], 400);
            }

            // Verify code
            if (session('verification_code') !== $enteredCode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid verification code. Please try again.'
                ], 400);
            }

            // Code is valid - clear verification session
            session()->forget([
                'verification_code',
                'verification_username',
                'verification_expires',
                'verification_last_sent'
            ]);

            // Set verification success flag
            session(['grades_verified' => true]);

            Log::info('Verification successful', ['username' => $username]);

            return response()->json([
                'success' => true,
                'message' => 'Verification successful!'
            ]);

        } catch (\Exception $e) {
            Log::error('Verify code error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Verification failed. Please try again.'
            ], 500);
        }
    }

    /** Send verification email */
    private function sendVerificationEmail($email, $code, $username)
    {
        try {
            $data = [
                'code' => $code,
                'username' => $username,
                'expires_in' => '5 minutes'
            ];

            Mail::send('emails.verification-code', $data, function ($message) use ($email) {
                $message->to($email)
                        ->subject('Your Verification Code - Batangas State University');
            });

            Log::info('Verification email sent to: ' . $email);

        } catch (\Exception $e) {
            Log::error('Email sending failed: ' . $e->getMessage());
            throw new \Exception('Failed to send email: ' . $e->getMessage());
        }
    }

    /** Get filtered grades by AY/Semester (for Grades modal) */
    public function getGradesModal(Request $request)
    {
        try {
            Log::info('getGradesModal called', ['params' => $request->all()]);

            if (!$request->expectsJson() && !$request->ajax()) {
                return redirect()->route('student.grades.index');
            }

            $studentId = $this->getStudentId();
            if (!$studentId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student record not found.',
                    'html'    => '',
                ], 404);
            }

            // Get filter parameters
            $ayId = $request->query('ay_id');
            $semRaw = $request->query('sem');

            // Clean semester parameter
            if ($semRaw) {
                $semRaw = preg_replace('/:.*$/', '', $semRaw);
            }

            Log::info('Filtered grades request', [
                'student_id' => $studentId,
                'ay_id' => $ayId,
                'sem_raw' => $semRaw
            ]);

            // Build query for filtered grades
            $query = StudentGrade::with(['curriculumSubject:subject_id,Code,Course_Title,units', 'academicYear'])
                ->where('Student_id', $studentId);

            // Apply filters
            if ($ayId && $semRaw) {
                $semNorm = $this->normalizeSemester($semRaw);
                
                $query->where('academic_year_id', $ayId)
                      ->where('semester', $semNorm);
            }

            $grades = $query->get();

            if ($grades->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No grades found for the selected academic year and semester.',
                    'html'    => '',
                ]);
            }

            // Format grades for JavaScript rendering
            $formattedGrades = $grades->map(function (StudentGrade $g) {
                $cs = $g->curriculumSubject;
                
                return [
                    'course_code' => $g->course_code ?: ($cs->Code ?? 'N/A'),
                    'course_name' => $g->course_title ?: ($cs->Course_Title ?? 'Course Name Not Available'),
                    'units' => $cs->units ?? null,
                    'grade' => $g->grade ?? 'N/A',
                    'instructor' => $g->instructor ?? 'Instructor Not Available',
                ];
            });

            $ayLabel = $this->getAcademicYearLabelById($ayId);
            $semesterHuman = $this->toHumanSemester($semRaw);

            return response()->json([
                'success' => true,
                'grades' => $formattedGrades,
                'ay_label' => $ayLabel,
                'semester_human' => $semesterHuman,
                'total_grades' => $grades->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('getGradesModal error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'params' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while loading grades.',
                'html' => ''
            ], 500);
        }
    }

    /** Get all grades for View All Grades modal */
    public function getAllGradesModal(Request $request)
    {
        try {
            // Temporarily remove verification check for testing
            // if (!session('grades_verified')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Please verify your identity first.',
            //         'requires_verification' => true
            //     ], 403);
            // }

            if (!$request->expectsJson() && !$request->ajax()) {
                return redirect()->route('student.grades.index');
            }

            $studentId = $this->getStudentId();
            if (!$studentId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student record not found.',
                    'html'    => '',
                ], 404);
            }

            Log::info('getAllGradesModal called', ['student_id' => $studentId]);

            // Get ALL grades (no filters)
            $grades = StudentGrade::with(['curriculumSubject:subject_id,Code,Course_Title,units', 'academicYear'])
                ->where('Student_id', $studentId)
                ->get();

            if ($grades->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No academic records found.',
                    'html'    => '',
                ]);
            }

            // Get student profile
            $profile = $this->getStudentProfile($studentId);

            // Sort: newest AY first, then ordered per semester
            $grades = $grades->sortByDesc(function (StudentGrade $g) {
                $ayId  = (int) $g->academic_year_id;
                $label = $this->getAcademicYearLabelById($ayId);

                $startYear = $ayId;
                if (preg_match('/(\d{4})/', (string) $label, $m)) {
                    $startYear = (int) $m[1];
                }

                $semIdx = $this->semesterSortIndex($g->semester);
                return $startYear * 10 + $semIdx;
            });

            // Group by AY + normalized sem
            $grouped = $grades->groupBy(function (StudentGrade $g) {
                $ay   = (int) $g->academic_year_id;
                $semC = $this->normalizeSemester($g->semester ?? '');
                return $ay . '|' . $semC;
            });

            $html        = '';
            $overallRows = [];

            foreach ($grouped as $key => $rows) {
                [$ayIdStr, $semKey] = explode('|', $key, 2);
                $ayId     = (int) $ayIdStr;
                $ayLabel  = $this->getAcademicYearLabelById($ayId);

                $semHuman = $this->toHumanSemester($semKey);
                $upper    = strtoupper($semHuman);

                $labelSem = match ($upper) {
                    'FIRST'   => 'FIRST SEMESTER',
                    'SECOND'  => 'SECOND SEMESTER',
                    'MIDYEAR', 'MID YEAR', 'SUMMER' => 'SUMMER',
                    'SUMMER2' => 'SUMMER 2',
                    default   => $upper,
                };

                // TERM HEADER
                $html .= '
                    <div style="text-align:center;font-weight:bold;font-size:12px;margin:6px 0 2px;">
                        ' . e($labelSem) . ' AY ' . e($ayLabel) . '
                    </div>
                    <table style="width:100%;border-collapse:collapse;margin-bottom:4px;font-size:11px;">
                    <thead>
                        <tr>
                        <th style="border:1px solid #000;padding:3px 4px;width:16%;">Code</th>
                        <th style="border:1px solid #000;padding:3px 4px;">Description</th>
                        <th style="border:1px solid #000;padding:3px 4px;width:10%;">Credits</th>
                        <th style="border:1px solid #000;padding:3px 4px;width:10%;">Grade</th>
                        <th style="border:1px solid #000;padding:3px 4px;width:30%;">Instructor</th>
                        </tr>
                    </thead>
                    <tbody>
                ';

                $semRows   = [];
                $subjCount = 0;

                foreach ($rows as $g) {
                    $cs    = $g->curriculumSubject;
                    $code  = $g->course_code ?: ($cs->Code ?? '');
                    $name  = $g->course_title ?: ($cs->Course_Title ?? '');
                    $units = $cs->units ?? null;
                    $grade = $g->grade;
                    $inst  = $g->instructor;

                    $html .= '
                        <tr>
                        <td style="border:1px solid #000;padding:3px 4px;">' . e($code) . '</td>
                        <td style="border:1px solid #000;padding:3px 4px;">' . e($name) . '</td>
                        <td style="border:1px solid #000;padding:3px 4px;text-align:center;">' . e($units ?? '') . '</td>
                        <td style="border:1px solid #000;padding:3px 4px;text-align:center;">' . e($grade) . '</td>
                        <td style="border:1px solid #000;padding:3px 4px;">' . e($inst) . '</td>
                        </tr>
                    ';

                    $semRows[]     = ['units' => $units, 'grade' => $grade];
                    $overallRows[] = ['units' => $units, 'grade' => $grade];
                    $subjCount++;
                }

                $gwa = $this->computeGwa($semRows);

                // per-term footer row
                $html .= '
                    </tbody>
                    </table>
                    <table style="width:100%;border-collapse:collapse;margin-bottom:10px;font-size:11px;">
                    <tr>
                        <td style="border:1px solid #000;padding:3px 4px;width:70%;">Total Subjects : ' . $subjCount . '</td>
                        <td style="border:1px solid #000;padding:3px 4px;width:30%;">GWA : ' . ($gwa ?? 'N/A') . '</td>
                    </tr>
                    </table>
                ';
            }

            // Overall GWA
            $overallGwa = $this->computeGwa($overallRows);
            
            $html .= '
                <table style="width:100%;border-collapse:collapse;margin-top:6px;font-size:11px;">
                <tr>
                    <td style="border:1px solid #000;padding:4px 6px;font-weight:bold;width:70%;">General Weighted Average (GWA)</td>
                    <td style="border:1px solid #000;padding:4px 6px;width:30%;text-align:center;">' . ($overallGwa ?? 'N/A') . '</td>
                </tr>
                </table>
            ';

            return response()->json([
                'success'      => true,
                'html'         => $html,
                'profile'      => $profile,
                'generated_at' => now('Asia/Manila')->format('m/d/Y'),
            ]);

        } catch (\Exception $e) {
            Log::error('getAllGradesModal error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while loading all grades.',
                'html' => ''
            ], 500);
        }
    }

    /** Return HTML response for "View All Grades" */
    private function getAllGradesHtmlResponse($grades, $studentId)
    {
        // 2) Profile (SRCODE/FULLNAME/PROGRAM) - for Blade header
        $profile = $this->getStudentProfile($studentId);

        // 3) Sort: newest AY first, then ordered per semester
        $grades = $grades->sortByDesc(function (StudentGrade $g) {
            $ayId  = (int) $g->academic_year_id;
            $label = $this->getAcademicYearLabelById($ayId);

            $startYear = $ayId;
            if (preg_match('/(\d{4})/', (string) $label, $m)) {
                $startYear = (int) $m[1];
            }

            $semIdx = $this->semesterSortIndex($g->semester);
            return $startYear * 10 + $semIdx;
        });

        // 4) Group by AY + normalized sem
        $grouped = $grades->groupBy(function (StudentGrade $g) {
            $ay   = (int) $g->academic_year_id;
            $semC = $this->normalizeSemester($g->semester ?? '');
            return $ay . '|' . $semC;
        });

        $html        = '';
        $overallRows = [];

        foreach ($grouped as $key => $rows) {
            [$ayIdStr, $semKey] = explode('|', $key, 2);
            $ayId     = (int) $ayIdStr;
            $ayLabel  = $this->getAcademicYearLabelById($ayId);

            $semHuman = $this->toHumanSemester($semKey);
            $upper    = strtoupper($semHuman);

            $labelSem = match ($upper) {
                'FIRST'   => 'FIRST SEMESTER',
                'SECOND'  => 'SECOND SEMESTER',
                'MIDYEAR', 'MID YEAR', 'SUMMER' => 'SUMMER',
                'SUMMER2' => 'SUMMER 2',
                default   => $upper,
            };

            // TERM HEADER
            $html .= '
                <div style="text-align:center;font-weight:bold;font-size:12px;margin:6px 0 2px;">
                    ' . e($labelSem) . ' AY ' . e($ayLabel) . '
                </div>
                <table style="width:100%;border-collapse:collapse;margin-bottom:4px;font-size:11px;">
                <thead>
                    <tr>
                    <th style="border:1px solid #000;padding:3px 4px;width:16%;">Code</th>
                    <th style="border:1px solid #000;padding:3px 4px;">Description</th>
                    <th style="border:1px solid #000;padding:3px 4px;width:10%;">Credits</th>
                    <th style="border:1px solid #000;padding:3px 4px;width:10%;">Grade</th>
                    <th style="border:1px solid #000;padding:3px 4px;width:30%;">Instructor</th>
                    </tr>
                </thead>
                <tbody>
            ';

            $semRows   = [];
            $subjCount = 0;

            foreach ($rows as $g) {
                $cs    = $g->curriculumSubject;
                $code  = $g->course_code ?: ($cs->Code ?? '');
                $name  = $g->course_title ?: ($cs->Course_Title ?? '');
                $units = $cs->units ?? null;
                $grade = $g->grade;
                $inst  = $g->instructor;

                $html .= '
                    <tr>
                    <td style="border:1px solid #000;padding:3px 4px;">' . e($code) . '</td>
                    <td style="border:1px solid #000;padding:3px 4px;">' . e($name) . '</td>
                    <td style="border:1px solid #000;padding:3px 4px;text-align:center;">' . e($units ?? '') . '</td>
                    <td style="border:1px solid #000;padding:3px 4px;text-align:center;">' . e($grade) . '</td>
                    <td style="border:1px solid #000;padding:3px 4px;">' . e($inst) . '</td>
                    </tr>
                ';

                $semRows[]     = ['units' => $units, 'grade' => $grade];
                $overallRows[] = ['units' => $units, 'grade' => $grade];
                $subjCount++;
            }

            $gwa = $this->computeGwa($semRows);

            // per-term footer row
            $html .= '
                </tbody>
                </table>
                <table style="width:100%;border-collapse:collapse;margin-bottom:10px;font-size:11px;">
                <tr>
                    <td style="border:1px solid #000;padding:3px 4px;width:70%;">Total Subjects : ' . $subjCount . '</td>
                    <td style="border:1px solid #000;padding:3px 4px;width:30%;">GWA : ' . ($gwa ?? 'N/A') . '</td>
                </tr>
                </table>
            ';
        }

        // Overall GWA
        $overallGwa = $this->computeGwa($overallRows);
        
        $html .= '
            <table style="width:100%;border-collapse:collapse;margin-top:6px;font-size:11px;">
            <tr>
                <td style="border:1px solid #000;padding:4px 6px;font-weight:bold;width:70%;">General Weighted Average (GWA)</td>
                <td style="border:1px solid #000;padding:4px 6px;width:30%;text-align:center;">' . ($overallGwa ?? 'N/A') . '</td>
            </tr>
            </table>
        ';

        return response()->json([
            'success'      => true,
            'html'         => $html,
            'profile'      => $profile,
            'generated_at' => now('Asia/Manila')->format('m/d/Y'),
            'filter_type'  => 'all',
        ]);
    }

    /** Return JSON response for filtered grades (AY + Semester) */
    private function getFilteredGradesJsonResponse($grades, $ayId, $semRaw)
    {
        $formattedGrades = $grades->map(function (StudentGrade $g) {
            $cs = $g->curriculumSubject;
            
            return [
                'course_code' => $g->course_code ?: ($cs->Code ?? 'N/A'),
                'course_name' => $g->course_title ?: ($cs->Course_Title ?? 'Course Name Not Available'),
                'units' => $cs->units ?? null,
                'grade' => $g->grade ?? 'N/A',
                'instructor' => $g->instructor ?? 'Instructor Not Available',
            ];
        });

        $ayLabel = $this->getAcademicYearLabelById($ayId);
        $semesterHuman = $this->toHumanSemester($semRaw);

        return response()->json([
            'success' => true,
            'grades' => $formattedGrades,
            'ay_label' => $ayLabel,
            'semester_human' => $semesterHuman,
            'total_grades' => $grades->count(),
        ]);
    }

    /** Copy of grades (AJAX → JSON) – returns stored COG image */
    public function getCopyOfGradesModal(Request $request)
    {
        if (!$request->expectsJson() && !$request->ajax()) {
            return redirect()->route('student.grades.index');
        }

        $studentId = $this->getStudentId();
        if (!$studentId) {
            return response()->json([
                'success' => false,
                'message' => 'Student record not found.',
            ], 404);
        }

        // Galing sa JS (viewgrade.blade):
        // ay_label = "2023-2024"
        // sem      = "FIRST" / "SECOND" / "MIDYEAR" / "SUMMER" / "SUMMER2"
        $ayLabelRaw = trim((string) $request->query('ay_label', ''));
        $semRaw     = strtoupper(trim((string) $request->query('sem', '')));

        // Kung walang filter na pinasa, pde tayong mag-fallback sa latest upload
        if ($ayLabelRaw === '' || $semRaw === '') {
            $latest = Grades::forStudent($studentId)
                ->orderByDesc('Grades_id')
                ->first();

            if (!$latest || empty($latest->image)) {
                return response()->json([
                    'success'      => true,
                    'has_image'    => false,
                    'image_base64' => null,
                    'message'      => 'No Copy of Grades image found.',
                ]);
            }

            return response()->json([
                'success'      => true,
                'has_image'    => true,
                'image_base64' => $latest->image_base64,
                'sem'          => $latest->sem,
                'academic_year'=> $latest->academic_year,
            ]);
        }

        $semNorm = $this->normalizeSemester($semRaw);

        $aliases = match (strtoupper($semNorm)) {
            'MIDYEAR' => ['MIDYEAR', 'SUMMER'],
            default   => [$semNorm],
        };

        $record = Grades::forStudent($studentId)
            ->where('academic_year', $ayLabelRaw)
            ->whereIn('sem', $aliases)
            ->orderByDesc('Grades_id')
            ->first();

        if (!$record || empty($record->image)) {
            return response()->json([
                'success'      => true,
                'has_image'    => false,
                'image_base64' => null,
                'message'      => 'No Copy of Grades image found for selected AY & Semester.',
            ]);
        }

        return response()->json([
            'success'      => true,
            'has_image'    => true,
            'image_base64' => $record->image_base64,
            'sem'          => $record->sem,
            'academic_year'=> $record->academic_year,
        ]);
    }

    /** For AY picker (labels only) */
    public function getStudentAcademicYears(Request $request)
    {
        try {
            $studentId = $this->getStudentId();
            if (!$studentId) {
                return response()->json([
                    'success' => false,
                    'years'   => [],
                    'message' => 'Student not found',
                ], 404);
            }

            $ayIds = StudentGrade::query()
                ->where('Student_id', $studentId)
                ->select('academic_year_id')
                ->distinct()
                ->pluck('academic_year_id')
                ->filter();

            if ($ayIds->isEmpty()) {
                return response()->json(['success' => true, 'years' => []]);
            }

            $rows = DB::table('academic_years')->whereIn('academic_year_id', $ayIds)->get();
            $years = $rows->map(function ($r) {
                    $label = $r->label
                        ?? $r->academic_year
                        ?? $r->year_name
                        ?? $r->name
                        ?? (isset($r->start_year) || isset($r->end_year)
                            ? trim(($r->start_year ?? '') . '-' . ($r->end_year ?? ''), '-')
                            : null);

                    if (!$label) $label = 'AY #' . $r->academic_year_id;
                    return ['id' => (int) $r->academic_year_id, 'label' => (string) $label];
                })
                ->sortByDesc('label')
                ->values();

            return response()->json(['success' => true, 'years' => $years]);
        } catch (\Throwable $e) {
            Log::error('getStudentAcademicYears error', ['e' => $e->getMessage()]);
            return response()->json(['success' => false, 'years' => [], 'message' => 'Server error'], 500);
        }
    }

    /** HTML: all grades (history) – optional full-page route */
    public function showAllGrades(Request $request)
    {
        $studentId = $this->getStudentId();
        if (!$studentId) {
            return redirect()->back()->with('error', 'Student record not found.');
        }

        $grades = StudentGrade::with(['curriculumSubject', 'academicYear'])
            ->where('Student_id', $studentId)
            ->orderBy('academic_year_id', 'desc')
            ->orderBy('semester', 'desc')
            ->get()
            ->groupBy(['academic_year_id', 'semester']);

        return view('student.all-grades', compact('grades'));
    }

    /** HTML: printable copy of grades (table-based, optional) */
    public function showCopyOfGrades()
    {
        $studentId = $this->getStudentId();
        if (!$studentId) {
            return redirect()->back()->with('error', 'Student record not found.');
        }

        $grades = StudentGrade::with(['curriculumSubject'])
            ->where('Student_id', $studentId)
            ->where('academic_year_id', $this->getCurrentAcademicYear())
            ->where('semester', $this->getCurrentSemester())
            ->get()
            ->map(function (StudentGrade $g) {
                $cs    = $g->curriculumSubject;
                $units = $cs->units ?? null;

                return [
                    'course_code' => $g->course_code ?: ($cs->Code ?? null),
                    'course_name' => $g->course_title ?: ($cs->Course_Title ?? 'Course Title Not Available'),
                    'units'       => $units ?? 'N/A',
                    'instructor'  => $g->instructor,
                    'grade'       => $g->grade,
                ];
            });

        return view('student.copy-of-grades', compact('grades'));
    }

    /** Resolve Student_id from logged-in user (cached in session) */
    private function getStudentId()
    {
        if ($sid = session('Student_id')) {
            return (int) $sid;
        }

        $loginId = Auth::id();
        foreach (['student_manage', 'students', 'student_records'] as $table) {
            try {
                if (!DB::getSchemaBuilder()->hasTable($table)) continue;
                $student = DB::table($table)
                    ->where(function ($q) use ($loginId) {
                        $q->where('Login_id', $loginId)->orWhere('login_id', $loginId);
                    })
                    ->select(['Student_id', 'student_id', 'id'])
                    ->first();

                if ($student) {
                    $sid = $student->Student_id ?? $student->student_id ?? $student->id;
                    session(['Student_id' => (int) $sid]);
                    Log::info('Found student record', [
                        'login_id'   => $loginId,
                        'table'      => $table,
                        'student_id' => $sid,
                    ]);
                    return (int) $sid;
                }
            } catch (\Throwable $e) {
                Log::warning("Error checking table {$table}", ['error' => $e->getMessage()]);
            }
        }

        Log::error('Student record not found for login_id', ['login_id' => $loginId]);
        return null;
    }

    /** Current AY id with safe fallbacks */
    private function getCurrentAcademicYear()
    {
        try {
            if (class_exists(AcademicYear::class)) {
                if ($cur = AcademicYear::where('is_current', true)->first()) {
                    return (int) $cur->academic_year_id;
                }
                if ($latest = AcademicYear::orderBy('academic_year_id', 'desc')->first()) {
                    return (int) $latest->academic_year_id;
                }
            }
            $ay = DB::table('academic_years')
                ->orderByDesc('is_current')
                ->orderByDesc('academic_year_id')
                ->first();

            return $ay ? (int) $ay->academic_year_id : 1;
        } catch (\Throwable $e) {
            Log::warning('AY fallback used', ['e' => $e->getMessage()]);
            return 1;
        }
    }

    /** PH semester mapping (fallback) */
    private function getCurrentSemester()
    {
        try {
            $month = (int) now('Asia/Manila')->format('n');
        } catch (\Throwable $e) {
            $month = (int) date('n');
        }

        if ($month >= 8 && $month <= 12) return '1st';
        if ($month >= 1 && $month <= 5)  return '2nd';
        return 'midyear';
    }

    public function redirectToUpload()
    {
        return redirect()->route('student.studentgrade');
    }

    public function uploadGrades(Request $request)
    {
        return back()->with('success', 'Grades uploaded successfully');
    }

    /** Debug utility */
    public function debugGrades(Request $request)
    {
        $loginId      = Auth::id();
        $studentId    = $this->getStudentId();
        $studentFound = !is_null($studentId);

        $allGrades = $studentId
            ? StudentGrade::with(['curriculumSubject', 'academicYear'])
                ->where('Student_id', $studentId)->get()
            : collect();

        return response()->json([
            'login_id'          => $loginId,
            'student_id'        => $studentId,
            'student_found'     => $studentFound,
            'total_grades'      => $allGrades->count(),
            'grades'            => $allGrades->map(function (StudentGrade $g) {
                $cs    = $g->curriculumSubject;
                $units = $cs->units ?? null;

                return [
                    'student_grade_id'      => $g->student_grade_id,
                    'course_code'           => $g->course_code,
                    'subject_id'            => $g->subject_id,
                    'academic_year_id'      => $g->academic_year_id,
                    'academic_year_label'   => $this->getAcademicYearLabelById($g->academic_year_id),
                    'semester'              => $g->semester,
                    'grade'                 => $g->grade,
                    'instructor'            => $g->instructor,
                    'has_curriculum_subject'=> !is_null($cs),
                    'course_title'          => $g->course_title ?? ($cs->Course_Title ?? null),
                    'units'                 => $units,
                ];
            }),
            'current_academic_year' => $this->getCurrentAcademicYear(),
            'current_semester'      => $this->getCurrentSemester(),
        ]);
    }

    private function getStudentProfile(int $studentId): array
    {
        $profile = [
            'srcode'         => '',
            'fullname'       => '',
            'fullname_short' => '',
            'program'        => '',
        ];

        // ---------- PRIMARY PATH: Eloquent models ----------
        try {
            $student = StudentManage::with(['studentCourse.program'])->find($studentId);

            if ($student) {
                // SRCODE from student_manage
                $profile['srcode'] = (string) ($student->SRCODE ?? '');

                // Build "LAST, FIRST M."
                $ln = trim((string) ($student->Last_name   ?? ''));
                $fn = trim((string) ($student->First_name  ?? ''));
                $mn = trim((string) ($student->Middle_name ?? ''));

                $full = $ln;
                if ($fn !== '') {
                    $full = $ln ? ($ln . ', ' . $fn) : $fn;
                }
                if ($mn !== '') {
                    $full .= ' ' . mb_substr($mn, 0, 1) . '.';
                }

                $profile['fullname']       = $full !== '' ? $full : 'STUDENT NAME';
                $profile['fullname_short'] = $profile['fullname'];

                // Program from StudentCourse → Program
                $course = $student->studentCourse->first(); // hasMany, so take the first row
                if ($course && $course->program) {
                    $profile['program'] = (string) ($course->program->Program_name ?? '');
                } else {
                    // fallback: accessor on StudentManage (curriculumAy → program)
                    $profile['program'] = (string) ($student->program ?? '');
                }

                return $profile;
            }
        } catch (\Throwable $e) {
            Log::warning('getStudentProfile via StudentManage failed', [
                'student_id' => $studentId,
                'error'      => $e->getMessage(),
            ]);
        }

        // ---------- FALLBACK PATH: raw DB lookup in other tables ----------
        try {
            $schema = DB::getSchemaBuilder();

            // We already tried student_manage above, so just check other tables
            foreach (['students', 'student_records'] as $table) {
                if (!$schema->hasTable($table)) {
                    continue;
                }

                $cols = $schema->getColumnListing($table);

                if (in_array('Student_id', $cols, true)) {
                    $row = DB::table($table)->where('Student_id', $studentId)->first();
                } elseif (in_array('student_id', $cols, true)) {
                    $row = DB::table($table)->where('student_id', $studentId)->first();
                } else {
                    continue;
                }

                if (!$row) {
                    continue;
                }

                // SRCODE
                $profile['srcode'] = $row->SRCODE
                    ?? $row->Srcode
                    ?? $row->srcode
                    ?? ($row->sr_code ?? '');

                // FULLNAME
                if (isset($row->Fullname) || isset($row->fullname)) {
                    $profile['fullname'] = (string) ($row->Fullname ?? $row->fullname);
                } else {
                    $ln = $row->LastName   ?? $row->lastname   ?? $row->last_name   ?? '';
                    $fn = $row->FirstName  ?? $row->firstname  ?? $row->first_name  ?? '';
                    $mn = $row->MiddleName ?? $row->middlename ?? $row->middle_name ?? '';
                    $full = trim($ln . ', ' . $fn . ' ' . ($mn ? mb_substr($mn, 0, 1) . '.' : ''));
                    $profile['fullname'] = $full ?: 'STUDENT NAME';
                }

                $profile['fullname_short'] = $profile['fullname'];

                // PROGRAM (very best-effort)
                $profile['program'] = $row->Program
                    ?? $row->program
                    ?? $row->course
                    ?? $profile['program'];

                return $profile;
            }
        } catch (\Throwable $e) {
            Log::warning('getStudentProfile fallback failed', [
                'student_id' => $studentId,
                'error'      => $e->getMessage(),
            ]);
        }

        return $profile;
    }

    private function normalizeSemester(string $s): string
    {
        $key = strtolower(trim($s));
        $map = [
            '1'              => 'FIRST',
            '1st'            => 'FIRST',
            '1st sem'        => 'FIRST',
            'first semester' => 'FIRST',
            'first'          => 'FIRST',
            '2'              => 'SECOND',
            '2nd'            => 'SECOND',
            '2nd sem'        => 'SECOND',
            'second semester'=> 'SECOND',
            'second'         => 'SECOND',
            'midyear'        => 'MIDYEAR',
            'summer'         => 'MIDYEAR',  // ⬅️ IMPORTANT
        ];
        $val = $map[$key] ?? strtoupper($s ?: 'FIRST');
        return mb_substr($val, 0, 10);
    }

    /**
     * For a canonical sem ("1st","2nd","midyear","summer","summer2"),
     * return possible stored variants in the DB.
     */
    private function semesterAliases(string $semNorm): array
    {
        return match (strtoupper($semNorm)) {
            'FIRST'   => ['FIRST', '1st', 'first', '1st sem', 'first semester'],
            'SECOND'  => ['SECOND', '2nd', 'second', '2nd sem', 'second semester'],
            'MIDYEAR' => ['MIDYEAR', 'midyear', 'mid-year', 'mid'],
            'SUMMER'  => ['SUMMER', 'summer', 'summer term', 'summer1', '1st summer'],
            'SUMMER2' => ['SUMMER2', 'summer2', 'summer-2', 'summer_2', '2nd summer'],
            default   => [$semNorm],
        };
    }

    /**
     * Return sort index for a semester string.
     * Bigger number = mas bago sa loob ng same AY.
     */
    private function semesterSortIndex(?string $sem): int
    {
        $norm = $this->normalizeSemester($sem ?? '') ?? '';

        // Mas mataas na number = mas nasa itaas sa loob ng parehong AY
        return match (strtoupper($norm)) {
            'MIDYEAR' => 5, // pinaka-latest term
            'SECOND'  => 4,
            'FIRST'   => 3,
            'SUMMER2' => 2,
            'SUMMER'  => 1,
            default   => 0,
        };
    }

    /**
     * Compute weighted GWA from an iterable of ['units'=>x,'grade'=>y]
     * - Tinatanggap ang mga grade na may format na "INC/2.00", "2.00/INC", etc.
     * - Hahanapin niya yung unang valid numeric value sa string at iyon ang gagamitin.
     */
    private function computeGwa(iterable $rows): ?string
    {
        $totalWeight = 0.0;
        $totalUnits  = 0.0;

        foreach ($rows as $r) {
            $uRaw = $r['units'] ?? null;
            $gRaw = $r['grade'] ?? null;

            if ($uRaw === null || $gRaw === null) {
                continue;
            }

            // Units
            $u = (float) $uRaw;
            if ($u <= 0) {
                continue;
            }

            // Grade: "1.75", "INC/2.00", etc.
            $gStr = trim((string) $gRaw);
            $g    = null;

            if (is_numeric($gStr)) {
                $g = (float) $gStr;
            } else {
                // Extract first numeric part
                if (preg_match('/(\d+(\.\d+)?)/', $gStr, $m)) {
                    $g = (float) $m[1];
                }
            }

            if ($g === null || $g <= 0 || $g > 4.0) {
                continue;
            }

            $totalWeight += $g * $u;
            $totalUnits  += $u;
        }

        if ($totalUnits <= 0) {
            return null;
        }

        return number_format($totalWeight / $totalUnits, 4);
    }

    /**
     * Convert various semester representations to a human label.
     * Used by: getGradesModal(), getAllGradesModal(), debugGrades()
     */
    private function toHumanSemester(?string $sem): string
    {
        // Normalize first so "1st", "first", "FIRST" etc. map to the same value
        $norm = $this->normalizeSemester($sem ?? '');

        return match (strtoupper((string) $norm)) {
            'FIRST'   => 'FIRST',
            'SECOND'  => 'SECOND',
            'MIDYEAR' => 'MIDYEAR',
            'SUMMER'  => 'SUMMER',
            'SUMMER2' => 'SUMMER2',
            default   => strtoupper((string) $sem) ?: 'N/A',
        };
    }

    /**
     * Get a readable AY label from the ID.
     * Used by: getAllGradesModal(), debugGrades()
     */
    private function getAcademicYearLabelById(?int $id): string
    {
        if (!$id) {
            return '';
        }

        // Try via Eloquent model first
        try {
            if (class_exists(AcademicYear::class)) {
                $ay = AcademicYear::find($id);
                if ($ay) {
                    // Try several possible field names
                    if (!empty($ay->label)) {
                        return (string) $ay->label;
                    }
                    if (!empty($ay->academic_year)) {
                        return (string) $ay->academic_year;
                    }
                    if (!empty($ay->year_name)) {
                        return (string) $ay->year_name;
                    }
                    if (!empty($ay->name)) {
                        return (string) $ay->name;
                    }
                    if (!empty($ay->start_year) || !empty($ay->end_year)) {
                        return trim(($ay->start_year ?? '') . '-' . ($ay->end_year ?? ''), '-');
                    }
                }
            }
        } catch (\Throwable $e) {
            // fall through to DB lookup
            Log::warning('getAcademicYearLabelById via model failed', [
                'id'    => $id,
                'error' => $e->getMessage(),
            ]);
        }

        // Fallback raw DB lookup (handles weird column names)
        try {
            if (DB::getSchemaBuilder()->hasTable('academic_years')) {
                $row = DB::table('academic_years')
                    ->where('academic_year_id', $id)
                    ->orWhere('id', $id)
                    ->first();

                if ($row) {
                    $label =
                        $row->label
                        ?? $row->academic_year
                        ?? $row->year_name
                        ?? $row->name
                        ?? ((isset($row->start_year) || isset($row->end_year))
                            ? trim(($row->start_year ?? '') . '-' . ($row->end_year ?? ''), '-')
                            : null);

                    if ($label) {
                        return (string) $label;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('getAcademicYearLabelById via DB failed', [
                'id'    => $id,
                'error' => $e->getMessage(),
            ]);
        }

        // Last fallback
        return 'AY #' . $id;
    }
}