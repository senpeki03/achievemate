<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\GraduationForm;
use App\Models\StudentManage;
use App\Models\GraduationRequirement;
use App\Models\StudentCourse;
use App\Models\College;
use App\Models\Program;
use App\Models\Major;
use App\Models\StudentGrade;
use App\Models\UserDesignation;
use App\Models\Curriculumsubject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Smalot\PdfParser\Parser;
use setasign\Fpdi\Fpdi;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Log;

class GraduationFormController extends Controller
{
    private string $cogDir;
    private string $corDir;

    /** NEVER append COG text to the generated PDF */
    private bool $appendCogToPdf = false;

    public function __construct()
    {
        // These map to:
        // /home/.../AchieveMate/storage/graduation/{cog|cor}
        $this->cogDir = storage_path('graduation/cog');
        $this->corDir = storage_path('graduation/cor');
    }

    /* ----------------------------------------------------------------------
     |  VIEW (prefill + template)
     * --------------------------------------------------------------------*/
    public function show(Request $request)
    {
        $baseDir  = 'pdf_templates';
        $expected = 'BatStateU-FO-REG-10_Application for Graduation_Rev. 02.pdf';

        if (!Storage::disk('local')->exists($baseDir)) {
            Storage::disk('local')->makeDirectory($baseDir);
        }

        $templatePath = Storage::disk('local')->exists($baseDir . '/' . $expected)
            ? ($baseDir . '/' . $expected)
            : (collect(Storage::disk('local')->allFiles($baseDir))
                ->first(fn($p) => str_ends_with(strtolower($p), '.pdf')
                    && str_contains(strtolower($p), 'application for graduation')));

        $pdfUrl = $templatePath ? route('media', ['path' => ltrim($templatePath, '/')]) : null;

        // 🔹 Resolve student by Login_id
        $loginId = optional($request->user())->Login_id
            ?? session('login_id')
            ?? session('Login_id');

        $student = $loginId
            ? StudentManage::where('Login_id', $loginId)->first()
            : null;

        $formRow = $student
            ? GraduationForm::where('Student_id', $student->Student_id)->first()
            : null;

        $birthYmd = '';
        if ($formRow && $formRow->Birthdate) {
            try {
                $birthYmd = Carbon::parse($formRow->Birthdate)->format('Y-m-d');
            } catch (\Throwable) {
            }
        }

        // ---------- BASE PREFILL ----------
        $prefill = [
            // from student_manage
            'surname'        => $student->Last_name   ?? '',
            'first_name'     => $student->First_name  ?? '',
            'middle_name'    => $student->Middle_name ?? '',
            'sr_code'        => $student->SRCODE      ?? '',
            'contact_number' => $student->Contact     ?? '',
            'email'          => $student->Email       ?? '',

            // from graduation_form
            'birthdate'         => $birthYmd,
            'place_of_birth'    => $formRow->PlaceofBirth   ?? '',
            'home_address'      => $formRow->HomeAddress    ?? '',
            'zip_code'          => $formRow->ZIP_Code       ?? '',
            'secondary_school'  => $formRow->Sec_Grad       ?? '',
            'secondary_year'    => $formRow->Sec_Grad_Year  ?? '',
            'elementary_school' => $formRow->Elem_Grad      ?? '',
            'elementary_year'   => $formRow->Elem_Grad_Year ?? '',

            // Step 5 extras (DB-backed)
            'scholarship_grant' => $formRow->Scholarship_grant     ?? '',
            'parent1'           => $formRow->Guardian_1            ?? '',
            'parent1_contact'   => $formRow->Guardian_1_Contact    ?? '',
            'parent2'           => $formRow->Guardian_2            ?? '',
            'parent2_contact'   => $formRow->Guardian_2_Contact    ?? '',

            // display-only (to be resolved below)
            'college' => '',
            'program' => '',
            'major'   => '',
        ];

        /* ==========================================================
         * 1) MAIN SOURCE: student_course (College_id, Program_id, Major_id)
         *    via ELOQUENT RELATIONS
         * ========================================================*/
        if ($student) {
            $course = StudentCourse::with(['college', 'program', 'major'])
                ->where('Student_id', $student->Student_id)
                ->orderByDesc('StudentCourse_id')
                ->first();

            if ($course) {
                if ($course->college) {
                    $prefill['college'] = $course->college->College_name ?? '';
                }
                if ($course->program) {
                    $prefill['program'] = $course->program->Program_name ?? '';
                }
                if ($course->major) {
                    $prefill['major'] = $course->major->Major_name ?? '';
                }
            }
        }

        /* ==========================================================
         * 2) FALLBACK: curriculum / curriculum_ay chain
         * ========================================================*/
        try {
            if ($student && (empty($prefill['college']) || empty($prefill['program']) || empty($prefill['major']))) {
                $curriculumId = $student->curriculum_id ?? $student->Curriculum_id ?? null;
                if ($curriculumId) {
                    $curr = DB::table('curriculum')->where('curriculum_id', $curriculumId)->first();
                    $currAyId = $curr->CurriculumAY_id ?? ($curr->CurriculumAMY_id ?? null);

                    if ($currAyId) {
                        $cay = DB::table('curriculum_ay')->where('CurriculumAY_id', $currAyId)->first();
                        if ($cay) {
                            $college = $cay->College_id ? DB::table('college')->where('College_id', $cay->College_id)->first() : null;
                            $program = $cay->Program_id ? DB::table('program')->where('Program_id', $cay->Program_id)->first() : null;
                            $major   = $cay->Major_id   ? DB::table('major')->where('Major_id',   $cay->Major_id)->first()   : null;

                            if (empty($prefill['college']) && $college) {
                                $prefill['college'] = $college->College_name ?? $college->Name ?? '';
                            }
                            if (empty($prefill['program']) && $program) {
                                $prefill['program'] = $program->Program_name ?? $program->Name ?? '';
                            }
                            if (empty($prefill['major']) && $major) {
                                $prefill['major'] = $major->Major_name ?? $major->Name ?? '';
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // silent fallback lang
        }

        // ---------- FIELD DEFINITIONS ----------
        $fields = [
            'surname'        => ['t' => 13.6, 'l' => 1.6,  'w' => 22.5, 'type' => 'text',     'ph' => 'SURNAME'],
            'first_name'     => ['t' => 13.6, 'l' => 24.7, 'w' => 22.5, 'type' => 'text',     'ph' => 'FIRST NAME'],
            'middle_name'    => ['t' => 13.6, 'l' => 47.8, 'w' => 22.5, 'type' => 'text',     'ph' => 'MIDDLE NAME'],
            'extension_name' => ['t' => 13.6, 'l' => 70.9, 'w' => 27.5, 'type' => 'text',     'ph' => 'EXT. (JR/SR/etc)'],

            'sr_code'        => ['t' => 20.9, 'l' => 1.6,  'w' => 14.6, 'type' => 'text',     'ph' => 'SR CODE'],
            'birthdate'      => ['t' => 20.9, 'l' => 16.6, 'w' => 18.0, 'type' => 'text',     'ph' => 'MM/DD/YYYY'],
            'place_of_birth' => ['t' => 20.9, 'l' => 35.1, 'w' => 28.2, 'type' => 'text',     'ph' => 'PLACE OF BIRTH'],

            'home_address'   => ['t' => 27.6, 'l' => 1.6,  'w' => 47.8, 'type' => 'textarea', 'rows' => 3, 'ph' => 'HOME ADDRESS', 'h' => 8],
            'zip_code'       => ['t' => 27.6, 'l' => 50.1, 'w' => 10.0, 'type' => 'text',     'ph' => 'ZIP'],
            'contact_number' => ['t' => 31.3, 'l' => 50.1, 'w' => 25.0, 'type' => 'text',     'ph' => 'CONTACT NUMBER'],
            'email'          => ['t' => 35.1, 'l' => 50.1, 'w' => 25.0, 'type' => 'email',    'ph' => 'EMAIL ADDRESS'],

            'secondary_school' => ['t' => 41.5, 'l' => 1.6,  'w' => 47.8, 'type' => 'text', 'ph' => 'SECONDARY SCHOOL GRADUATED'],
            'secondary_year'   => ['t' => 41.5, 'l' => 50.1, 'w' => 10.0, 'type' => 'text', 'ph' => 'YEAR'],
            'elementary_school'=> ['t' => 46.0, 'l' => 1.6,  'w' => 47.8, 'type' => 'text', 'ph' => 'ELEMENTARY SCHOOL GRADUATED'],
            'elementary_year'  => ['t' => 46.0, 'l' => 50.1, 'w' => 10.0, 'type' => 'text', 'ph' => 'YEAR'],

            'grad_dec'       => ['t' => 52.0, 'l' => 18.8, 'w' => 3.0, 'type' => 'checkbox'],
            'grad_dec_year'  => ['t' => 52.0, 'l' => 27.0, 'w' => 9.0, 'type' => 'text'],
            'grad_may'       => ['t' => 52.0, 'l' => 42.0, 'w' => 3.0, 'type' => 'checkbox'],
            'grad_may_year'  => ['t' => 52.0, 'l' => 48.5, 'w' => 9.0, 'type' => 'text'],
            'grad_mid'       => ['t' => 52.0, 'l' => 64.0, 'w' => 3.0, 'type' => 'checkbox'],
            'grad_mid_year'  => ['t' => 52.0, 'l' => 74.0, 'w' => 9.0, 'type' => 'text'],

            'college'        => ['t' => 56.5, 'l' => 1.6, 'w' => 47.8, 'type' => 'text', 'ph' => 'COLLEGE'],
            'program'        => ['t' => 60.4, 'l' => 1.6, 'w' => 47.8, 'type' => 'text', 'ph' => 'PROGRAM'],
            'major'          => ['t' => 64.3, 'l' => 1.6, 'w' => 47.8, 'type' => 'text', 'ph' => 'MAJOR'],
        ];

        return view('student.graduationform', compact('pdfUrl', 'fields', 'prefill'));
    }

    /* ----------------------------------------------------------------------
    |  SAVE FORM FIELDS
    * --------------------------------------------------------------------*/
    public function saveFields(Request $request)
    {
        try {
            $login = $request->user();

            if (!$login) {
                abort(403, 'Unauthorized');
            }

            $student = StudentManage::where('Login_id', $login->Login_id)->first();

            if (!$student) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'Student record not found.',
                ], 404);
            }

            // 🔎 Validate incoming fields from the form (Step 5)
            $data = $request->validate([
                'birthdate'          => 'required|date',
                'place_of_birth'     => 'nullable|string|max:255',
                'home_address'       => 'nullable|string|max:255',
                'zip_code'           => 'nullable|string|max:20',
                'secondary_school'   => 'nullable|string|max:255',
                'secondary_year'     => 'nullable|string|max:50',
                'elementary_school'  => 'nullable|string|max:255',
                'elementary_year'    => 'nullable|string|max:50',
                'scholarship_grant'  => 'nullable|string|max:255',
                'parent1'            => 'nullable|string|max:255',
                'parent1_contact'    => 'nullable|string|max:50',
                'parent2'            => 'nullable|string|max:255',
                'parent2_contact'    => 'nullable|string|max:50',
            ]);

            // 🔁 Map FRONTEND field names → REAL DB column names
            $payload = [
                'Student_id'         => $student->Student_id,
                'Birthdate'          => $data['birthdate'],                // date
                'PlaceofBirth'       => $data['place_of_birth']   ?? null, // PlaceofBirth
                'HomeAddress'        => $data['home_address']     ?? null, // HomeAddress
                'ZIP_Code'           => $data['zip_code']         ?? null, // ZIP_Code
                'Sec_Grad'           => $data['secondary_school'] ?? null, // Sec_Grad
                'Sec_Grad_Year'      => $data['secondary_year']   ?? null, // Sec_Grad_Year
                'Elem_Grad'          => $data['elementary_school']?? null, // Elem_Grad
                'Elem_Grad_Year'     => $data['elementary_year']  ?? null, // Elem_Grad_Year
                'Scholarship_grant'  => $data['scholarship_grant']?? null, // Scholarship_grant
                'Guardian_1'         => $data['parent1']          ?? null, // Guardian_1
                'Guardian_1_Contact' => $data['parent1_contact']  ?? null, // Guardian_1_Contact
                'Guardian_2'         => $data['parent2']          ?? null, // Guardian_2
                'Guardian_2_Contact' => $data['parent2_contact']  ?? null, // Guardian_2_Contact
            ];

            // 🔄 Create or update 1 row per student
            $form = GraduationForm::updateOrCreate(
                ['Student_id' => $student->Student_id],
                $payload
            );

            Log::info('GraduationForm saveFields: saved successfully', [
                'student_id'          => $student->Student_id,
                'graduation_form_id'  => $form->GraduationForm_id,
            ]);

            return response()->json([
                'ok'  => true,
                'id'  => $form->GraduationForm_id,
                'row' => $payload,
            ]);
        } catch (ValidationException $e) {
            // Para naka-proper 422 with errors
            throw $e;
        } catch (\Throwable $e) {
            Log::error('GraduationForm saveFields failed', [
                'err'   => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'ok'      => false,
                'message' => 'Server error while saving graduation form.',
            ], 500);
        }
    }

    /* ----------------------------------------------------------------------
     |  PROCESS GRADES FOR GRADUATION
     * --------------------------------------------------------------------*/
    /** API endpoint for graduation form to fetch grades (bypasses verification) */
    public function processGradesForGraduation(Request $request)
    {
        try {
            $loginId = optional($request->user())->Login_id
                ?? session('login_id')
                ?? session('Login_id');

            $student = $loginId ? StudentManage::where('Login_id', $loginId)->first() : null;
            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student record not found.',
                ], 404);
            }

            $studentId = $student->Student_id;

            // Get all grades for the student from student_grades table
            $grades = DB::table('student_grades')
                ->leftJoin('curriculum_subjects', 'student_grades.subject_id', '=', 'curriculum_subjects.subject_id')
                ->leftJoin('academic_years', 'student_grades.academic_year_id', '=', 'academic_years.academic_year_id')
                ->where('student_grades.Student_id', $studentId)
                ->select(
                    'student_grades.course_code',
                    'student_grades.course_title',
                    'curriculum_subjects.units',
                    'student_grades.grade',
                    'student_grades.semester',
                    'academic_years.label as academic_year_label',
                    'student_grades.instructor'
                )
                ->get();

            if ($grades->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No academic records found.',
                ]);
            }

            // Process grades for graduation form
            $processedGrades = $grades->map(function ($grade) {
                return [
                    'course_code' => $grade->course_code ?? '',
                    'course_title' => $grade->course_title ?? '',
                    'units' => $grade->units ?? null,
                    'grade' => $grade->grade,
                    'academic_year' => $grade->academic_year_label ?? '',
                    'semester' => $grade->semester ?? '',
                    'instructor' => $grade->instructor ?? '',
                ];
            });

            // Calculate overall GWA
            $totalWeight = 0.0;
            $totalUnits = 0.0;

            foreach ($processedGrades as $grade) {
                $units = floatval($grade['units'] ?? 0);
                $gradeValue = $this->extractGradeValue($grade['grade']);

                if ($units > 0 && $gradeValue !== null && $gradeValue > 0 && $gradeValue <= 4.0) {
                    $totalWeight += $gradeValue * $units;
                    $totalUnits += $units;
                }
            }

            $overallGwa = $totalUnits > 0 ? number_format($totalWeight / $totalUnits, 4) : null;

            return response()->json([
                'success' => true,
                'grades' => $processedGrades,
                'overall_gwa' => $overallGwa,
                'student_id' => $studentId,
                'total_subjects' => $grades->count(),
                'student_info' => [
                    'srcode' => $student->SRCODE ?? '',
                    'fullname' => $this->formattedStudentName($student),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('processGradesForGraduation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to process grades for graduation.',
            ], 500);
        }
    }

    /** Extract numeric grade value from grade string */
    private function extractGradeValue(?string $gradeRaw): ?float
    {
        if (!$gradeRaw) {
            return null;
        }

        $gradeStr = trim((string) $gradeRaw);
        
        // If it's directly numeric
        if (is_numeric($gradeStr)) {
            return (float) $gradeStr;
        }

        // Extract first numeric part from strings like "INC/2.00", "2.00/INC", etc.
        if (preg_match('/(\d+(\.\d+)?)/', $gradeStr, $matches)) {
            return (float) $matches[1];
        }

        return null;
    }

    /* ----------------------------------------------------------------------
     |  GENERATE PDF (PAGE 1 ONLY)
     * --------------------------------------------------------------------*/
    public function generateGradPdf(Request $request): JsonResponse
    {
        $loginId = optional($request->user())->Login_id
            ?? session('login_id')
            ?? session('Login_id');

        $student = $loginId ? StudentManage::where('Login_id', $loginId)->first() : null;
        if (!$student) {
            return response()->json(['ok' => false, 'message' => 'No linked student'], 401);
        }

        $form = GraduationForm::where('Student_id', $student->Student_id)->first();
        if (!$form) {
            return response()->json(['ok' => false, 'message' => 'Nothing to print yet. Save first.'], 422);
        }

        // 🔹 full name "First MiddleInitial. Last"
        $fullName = $this->formattedStudentName($student);

        // 🔹 ---- GET COLLEGE / PROGRAM / MAJOR (same logic as in show()) ----
        $collegeName = '';
        $programName = '';
        $majorName   = '';
        $campusId    = null;
        $collegeId   = null;

        $course = StudentCourse::with(['college', 'program', 'major'])
            ->where('Student_id', $student->Student_id)
            ->orderByDesc('StudentCourse_id')
            ->first();

        if ($course) {
            $campusId  = $course->Campus_id ?? null;
            $collegeId = $course->College_id ?? null;

            if ($course->college) {
                $collegeName = $course->college->College_name ?? '';
            }
            if ($course->program) {
                $programName = $course->program->Program_name ?? '';
            }
            if ($course->major) {
                $majorName = $course->major->Major_name ?? '';
            }
        }

        // fallback via curriculum_ay kung hindi pa rin kumpleto
        if ($student && ($collegeName === '' || $programName === '' || $majorName === '')) {
            try {
                $curriculumId = $student->curriculum_id ?? $student->Curriculum_id ?? null;
                if ($curriculumId) {
                    $curr = DB::table('curriculum')->where('curriculum_id', $curriculumId)->first();
                    $currAyId = $curr->CurriculumAY_id ?? ($curr->CurriculumAMY_id ?? null);

                    if ($currAyId) {
                        $cay = DB::table('curriculum_ay')->where('CurriculumAY_id', $currAyId)->first();
                        if ($cay) {
                            if ($collegeName === '' && $cay->College_id) {
                                $college = DB::table('college')->where('College_id', $cay->College_id)->first();
                                if ($college) {
                                    $collegeName = $college->College_name ?? $college->Name ?? '';
                                }
                            }
                            if ($programName === '' && $cay->Program_id) {
                                $program = DB::table('program')->where('Program_id', $cay->Program_id)->first();
                                if ($program) {
                                    $programName = $program->Program_name ?? $program->Name ?? '';
                                }
                            }
                            if ($majorName === '' && $cay->Major_id) {
                                $major = DB::table('major')->where('Major_id', $cay->Major_id)->first();
                                if ($major) {
                                    $majorName = $major->Major_name ?? $major->Name ?? '';
                                }
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                // ignore, best-effort lang
            }
        }
        // 🔹 ---- END GET COLLEGE / PROGRAM / MAJOR ----

        // 🔹 ---- GET REGISTRAR (by Campus) & DEAN (by College) ----
        $registrarName = '';
        $deanName      = '';

        if ($campusId) {
            $registrarDesignation = UserDesignation::with(['user', 'designation'])
                ->where('Campus_id', $campusId)
                ->whereHas('designation', function ($q) {
                    $q->whereRaw("UPPER(Designation_name) LIKE '%REGISTRAR%'");
                })
                ->first();

            if ($registrarDesignation && $registrarDesignation->user) {
                $registrarName = $registrarDesignation->user->full_name;
            }
        }

        if ($collegeId) {
            $deanDesignation = UserDesignation::with(['user', 'designation'])
                ->where('College_id', $collegeId)
                ->whereHas('designation', function ($q) {
                    $q->whereRaw("UPPER(Designation_name) LIKE '%DEAN%'");
                })
                ->first();

            if ($deanDesignation && $deanDesignation->user) {
                $deanName = $deanDesignation->user->full_name;
            }
        }
        // 🔹 ---- END REGISTRAR / DEAN LOOKUP ----

        $templatePath = storage_path('app/pdf_templates/BatStateU-FO-REG-10_Application for Graduation_Rev. 02.pdf');
        if (!file_exists($templatePath)) {
            return response()->json(['ok' => false, 'message' => 'Template not found'], 404);
        }

        $outDir = storage_path('app/public/pdf_output');
        if (!is_dir($outDir)) {
            @mkdir($outDir, 0775, true);
        }
        $gradId   = $form->GraduationForm_id;
        $fileName = 'graduation_form_' . $gradId . '.pdf';
        $outPath  = $outDir . DIRECTORY_SEPARATOR . $fileName;

        try {
            $pdf = new FPDI();
            $pdf->SetAutoPageBreak(false);

            $pdf->setSourceFile($templatePath);
            $tplIdx = $pdf->importPage(1);
            $size   = $pdf->getTemplateSize($tplIdx);

            $w = $size['width']  ?? ($size['w'] ?? 210);
            $h = $size['height'] ?? ($size['h'] ?? 297);
            $orientation = $size['orientation'] ?? ($w > $h ? 'L' : 'P');

            $pdf->AddPage($orientation, [$w, $h]);
            $pdf->useTemplate($tplIdx, 0, 0, $w);

            $pdf->SetFont('Times', '', 12);

            $put = function ($x, $y, $text) use ($pdf) {
                if ($text === null) {
                    $text = '';
                }
                $pdf->SetXY($x, $y);
                $pdf->Write(5, (string) $text);
            };

            // ---- existing fields ----
            $put(20,  47, $student->Last_name   ?? '');
            $put(75,  47, $student->First_name  ?? '');
            $put(130, 47, $student->Middle_name ?? '');

            $put(32,   58.5, $student->SRCODE ?? '');
            $put(87,   58.5, $form->Birthdate ? Carbon::parse($form->Birthdate)->format('m/d/Y') : '');
            $put(153,  58.5, $form->PlaceofBirth ?? '');

            $put(18,  69.5, $form->HomeAddress ?? '');
            $put(150, 64.5, $form->ZIP_Code ?? '');
            $put(150, 69.5, $student->Contact ?? '');
            $put(150, 78,   $student->Email ?? '');

            $put(74,  87, $form->Sec_Grad ?? '');
            $put(185, 87, $form->Sec_Grad_Year ?? '');
            $put(74,  96, $form->Elem_Grad ?? '');
            $put(185, 96, $form->Elem_Grad_Year ?? '');

            // 🔹 COLLEGE / PROGRAM / MAJOR
            $put(60, 109.0, $collegeName);  // COLLEGE:
            $put(60, 113.5, $programName);  // PROGRAM:
            $put(60, 118.0, $majorName);    // MAJOR:

            // 🔹 Full name over "Signature over Printed Name of Student"
            $put(32, 140.0, $fullName);

            $put(15, 220, $fullName);

            // 🔹 Date Signed
            $applyDate    = $form->created_at ?? $form->Created_at ?? null;
            $applyDateStr = $applyDate
                ? Carbon::parse($applyDate)->format('m/d/Y')
                : Carbon::now()->format('m/d/Y');

            $put(46, 151.0, $applyDateStr);

            // 🔹 Dean of College (adjust coordinates to match your template)
            // Example: left bottom signature block
            if (!empty($deanName)) {
                $put(35, 170.0, strtoupper($deanName));
            }

            // 🔹 Campus Registrar
            if (!empty($registrarName)) {
                $put(140, 140.0, strtoupper($registrarName));
            }

            if (!empty($registrarName)) {
                $put(140, 170.0, strtoupper($registrarName));
            }

            $check = function($x, $y) use ($pdf) {
                $pdf->SetFont('ZapfDingbats','', 14);
                $pdf->SetXY($x, $y);
                $pdf->Cell(5,5, '4', 0, 0); // ✔
                $pdf->SetFont('Helvetica','',10);
            };

            $check(10, 200);


            // Page 2 (COG) intentionally NOT appended
            $pdf->Output($outPath, 'F');

            $publicUrl = Storage::url('pdf_output/' . $fileName);
            return response()->json(['ok' => true, 'url' => $publicUrl, 'path' => $outPath]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'      => false,
                'message' => 'PDF error: ' . $e->getMessage(),
            ], 500);
        }
    }


    public function uploadCor(Request $request)
    {
        $request->validate([
            'cor' => ['required', 'file', 'mimetypes:application/pdf', 'max:51200'],
        ], [
            'mimetypes' => 'The file must be a PDF.',
        ]);

        File::ensureDirectoryExists($this->corDir, 0755, true);

        $pdfPath = rtrim($this->corDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'cor_uploaded.pdf';
        $txtPath = rtrim($this->corDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'cor_output.txt';

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('cor');
        @unlink($pdfPath);
        $file->move($this->corDir, 'cor_uploaded.pdf');

        // === Extract text with Smalot\PdfParser\Parser ===
        $extracted = '';

        try {
            $parser = new Parser();
            $pdf    = $parser->parseFile($pdfPath);
            $text   = $pdf->getText() ?? '';

            $text      = str_replace(["\r\n", "\r"], "\n", $text);
            $extracted = trim($text);

            if ($extracted === '') {
                $extracted = "/* COR extraction produced empty text. Check if the PDF is a scanned image. */";
            }
        } catch (\Throwable $e) {
            \Log::error('COR Smalot extract failed', [
                'err'  => $e->getMessage(),
                'file' => $pdfPath,
            ]);

            $extracted = "/* Error extracting COR text: " . $e->getMessage() . " */";
        }

        // Save raw text
        @File::put($txtPath, $extracted);

        // 🔎 Extract academic information from COR text
        $academicInfo = $this->extractAcademicInfoFromCorText($extracted);
        
        $loginId = optional($request->user())->Login_id
            ?? session('login_id')
            ?? session('Login_id');

        $student = $loginId ? StudentManage::where('Login_id', $loginId)->first() : null;

        $studentUpdated = false;
        if ($student) {
            Log::info('Attempting to update student academic info', [
                'student_id' => $student->Student_id,
                'academic_info_detected' => $academicInfo,
                'current_student_data' => [
                    'Year' => $student->Year,
                    'Academic_year' => $student->Academic_year
                ]
            ]);
            
            $studentUpdated = $this->updateStudentAcademicInfo($student, $academicInfo);
            
            Log::info('Student update result', [
                'student_id' => $student->Student_id,
                'updated' => $studentUpdated,
                'academic_info' => $academicInfo
            ]);
        } else {
            Log::warning('Student not found for academic info update', ['login_id' => $loginId]);
        }

        // 🔎 Try to detect scholarship line from the extracted COR text
        $scholarship = $this->extractScholarshipFromCorText($extracted);

        // 🔎 Evaluate graduation status from COR courses
        $graduationEvaluation = null;

        if ($student && $extracted && !str_contains($extracted, '/* COR extraction produced empty text')) {
            try {
                $corCourses = $this->extractCoursesFromCorText($extracted);
                $graduationEvaluation = $this->evaluateGraduationStatus($student, $corCourses);
                $this->saveGraduationEvaluation($student, $graduationEvaluation);
            } catch (\Exception $e) {
                Log::error('Auto graduation evaluation failed: ' . $e->getMessage());
            }
        }

        // If we found scholarship, UPDATE ONLY if graduation_form row already exists
        if ($scholarship && $student) {
            $form = GraduationForm::where('Student_id', $student->Student_id)->first();

            if ($form) {
                $form->Scholarship_grant = $scholarship;
                $form->save();
            } else {
                \Log::info('uploadCor: graduation_form row does not exist yet, skipping scholarship DB update', [
                    'student_id'  => $student->Student_id,
                    'scholarship' => $scholarship,
                ]);
            }
        }

        // page count
        $pages = $this->countPagesSmalot($pdfPath);

        return response()->json([
            'ok'                => true,
            'page_count'        => $pages,
            'cor_pdf_path'      => $pdfPath,
            'cor_text_path'     => $txtPath,
            'pdf_path'          => $pdfPath,
            'txt_path'          => $txtPath,
            'cor_output'        => basename($txtPath),
            'scholarship_grant' => $scholarship,
            'graduation_evaluation' => $graduationEvaluation,
            'courses_found'     => $graduationEvaluation ? count($graduationEvaluation['cor_courses'] ?? []) : 0,
            'academic_info'     => $academicInfo,
            'student_updated'   => $studentUpdated,
            'student_data_after' => $student ? [
                'Year' => $student->Year,
                'Academic_year' => $student->Academic_year
            ] : null
        ]);
    }

    /**
     * Extract academic information from COR text
     */
    private function extractAcademicInfoFromCorText(string $corText): array
    {
        $academicYear = '';
        $semester = '';
        $yearLevel = '';

        $lines = explode("\n", $corText);
        
        Log::info('Starting COR academic info extraction', ['total_lines' => count($lines)]);
        
        foreach ($lines as $index => $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Log important lines for debugging
            if (preg_match('/(Program:|FIRST,|SEMESTER|YEAR|FOURTH|THIRD|SECOND|FIRST)/i', $line)) {
                Log::debug('Important COR line', ['line_num' => $index, 'line' => $line]);
            }
            
            // 1. Detect Semester and Academic Year from "FIRST, 2025-2026"
            if (preg_match('/(FIRST|SECOND|SUMMER),\s*(\d{4}-\d{4})/i', $line, $matches)) {
                $semester = strtoupper($matches[1]);
                $academicYear = $matches[2];
                Log::info('Semester and Academic Year detected', [
                    'semester' => $semester, 
                    'academic_year' => $academicYear,
                    'line' => $line
                ]);
            }
            
            // 2. Detect Year Level from Program field - THIS IS THE KEY FIX!
            // Look for "Program:BS Information Technology -BA/FOURTH"
            if (preg_match('/Program:.*\/(FOURTH|THIRD|SECOND|FIRST|4TH|3RD|2ND|1ST)/i', $line, $matches)) {
                $detectedYear = strtoupper($matches[1]);
                
                // Map to full year name
                $yearMapping = [
                    'FOURTH' => 'FOURTH YEAR',
                    'THIRD' => 'THIRD YEAR', 
                    'SECOND' => 'SECOND YEAR',
                    'FIRST' => 'FIRST YEAR',
                    '4TH' => 'FOURTH YEAR',
                    '3RD' => 'THIRD YEAR',
                    '2ND' => 'SECOND YEAR',
                    '1ST' => 'FIRST YEAR'
                ];
                
                if (isset($yearMapping[$detectedYear])) {
                    $yearLevel = $yearMapping[$detectedYear];
                    Log::info('Year level detected from Program field', [
                        'detected' => $detectedYear,
                        'mapped_to' => $yearLevel,
                        'line' => $line
                    ]);
                }
            }
            
            // 3. Alternative: Look for year level in any context
            if (empty($yearLevel) && preg_match('/\b(FOURTH|THIRD|SECOND|FIRST|4TH|3RD|2ND|1ST)\s+YEAR\b/i', $line, $matches)) {
                $yearLevel = strtoupper($matches[1] . ' YEAR');
                Log::info('Year level detected from standalone mention', [
                    'year_level' => $yearLevel,
                    'line' => $line
                ]);
            }
            
            // 4. Academic year fallback patterns
            if (empty($academicYear)) {
                if (preg_match('/(\d{4}-\d{4})/', $line, $matches)) {
                    $academicYear = $matches[1];
                    Log::info('Academic year detected from generic pattern', [
                        'academic_year' => $academicYear,
                        'line' => $line
                    ]);
                }
            }
        }

        $result = [
            'academic_year' => $academicYear,
            'semester' => $semester,
            'year_level' => $yearLevel
        ];

        Log::info('COR Academic Info Extraction Complete', $result);

        return $result;
    }

    /**
     * Update student's academic information in StudentManage table
     */
    private function updateStudentAcademicInfo(StudentManage $student, array $academicInfo): bool
    {
        try {
            $updates = [];
            $changes = [];
            
            Log::info('Starting student academic info update', [
                'student_id' => $student->Student_id,
                'current_data' => [
                    'Year' => $student->Year,
                    'Academic_year' => $student->Academic_year
                ],
                'detected_info' => $academicInfo
            ]);
            
            // Update Academic_year if detected and different from current
            if (!empty($academicInfo['academic_year'])) {
                if ($student->Academic_year !== $academicInfo['academic_year']) {
                    $updates['Academic_year'] = $academicInfo['academic_year'];
                    $changes['Academic_year'] = [
                        'from' => $student->Academic_year,
                        'to' => $academicInfo['academic_year']
                    ];
                    Log::info('Academic year update needed', $changes['Academic_year']);
                } else {
                    Log::info('Academic year already matches', [
                        'current' => $student->Academic_year,
                        'detected' => $academicInfo['academic_year']
                    ]);
                }
            }
            
            // Update Year (year level) if detected - STORE AS TEXT "FOURTH YEAR" INSTEAD OF "4"
            if (!empty($academicInfo['year_level'])) {
                // Map to consistent year level text format
                $yearMapping = [
                    'FIRST YEAR' => 'FIRST YEAR',
                    'SECOND YEAR' => 'SECOND YEAR', 
                    'THIRD YEAR' => 'THIRD YEAR',
                    'FOURTH YEAR' => 'FOURTH YEAR',
                    'FIFTH YEAR' => 'FIFTH YEAR',
                    '1ST YEAR' => 'FIRST YEAR',
                    '2ND YEAR' => 'SECOND YEAR',
                    '3RD YEAR' => 'THIRD YEAR',
                    '4TH YEAR' => 'FOURTH YEAR',
                    '5TH YEAR' => 'FIFTH YEAR',
                    'FIRST' => 'FIRST YEAR',
                    'SECOND' => 'SECOND YEAR',
                    'THIRD' => 'THIRD YEAR',
                    'FOURTH' => 'FOURTH YEAR',
                    'FIFTH' => 'FIFTH YEAR'
                ];
                
                $yearLevel = strtoupper(trim($academicInfo['year_level']));
                $newYear = $yearMapping[$yearLevel] ?? $yearLevel; // Use mapped value or original if not found
                
                Log::info('Processing year level for update', [
                    'detected_year_level' => $yearLevel,
                    'mapped_year' => $newYear
                ]);
                
                if ($student->Year != $newYear) {
                    $updates['Year'] = $newYear;
                    $changes['Year'] = [
                        'from' => $student->Year,
                        'to' => $newYear
                    ];
                    Log::info('Year level update needed', $changes['Year']);
                } else {
                    Log::info('Year level already matches', [
                        'current' => $student->Year,
                        'detected' => $newYear
                    ]);
                }
            }
            
            if (!empty($updates)) {
                Log::info('Attempting to update student', [
                    'student_id' => $student->Student_id,
                    'updates' => $updates
                ]);
                
                $result = $student->update($updates);
                
                if ($result) {
                    Log::info('Student academic info updated successfully', [
                        'student_id' => $student->Student_id,
                        'changes' => $changes
                    ]);
                    return true;
                } else {
                    Log::error('Student update failed', [
                        'student_id' => $student->Student_id,
                        'updates' => $updates
                    ]);
                    return false;
                }
            } else {
                Log::info('No student updates needed', [
                    'student_id' => $student->Student_id,
                    'reason' => 'No changes detected or no academic info found'
                ]);
                return false;
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to update student academic info: ' . $e->getMessage(), [
                'student_id' => $student->Student_id,
                'academic_info' => $academicInfo
            ]);
            return false;
        }
    }

    /* ----------------------------------------------------------------------
    |  GRADUATION EVALUATION SYSTEM
    * --------------------------------------------------------------------*/

    /**
     * Extract course codes from COR text and evaluate graduation status
     */
    public function evaluateGraduationFromCor(Request $request)
    {
        try {
            $loginId = optional($request->user())->Login_id
                ?? session('login_id')
                ?? session('Login_id');

            $student = $loginId ? StudentManage::where('Login_id', $loginId)->first() : null;
            if (!$student) {
                return response()->json(['ok' => false, 'message' => 'Student not found'], 404);
            }

            // Get the COR text that was saved in step 2
            $corTextPath = $this->corDir . '/cor_output.txt';
            
            if (!file_exists($corTextPath)) {
                return response()->json([
                    'ok' => false, 
                    'message' => 'COR text not found. Please upload COR first.'
                ], 404);
            }

            $corText = file_get_contents($corTextPath);
            
            // Extract courses from COR
            $corCourses = $this->extractCoursesFromCorText($corText);
            
            // Evaluate graduation status
            $evaluation = $this->evaluateGraduationStatus($student, $corCourses);
            
            // Save evaluation to graduation requirements if form exists
            $this->saveGraduationEvaluation($student, $evaluation);

            return response()->json([
                'ok' => true,
                'evaluation' => $evaluation,
                'cor_courses_found' => count($corCourses),
                'student' => [
                    'student_id' => $student->Student_id,
                    'srcode' => $student->SRCODE,
                    'name' => $this->formattedStudentName($student),
                    'curriculum_id' => $student->curriculum_id
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Graduation evaluation error: ' . $e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Evaluation failed'], 500);
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
                        'code' => $code,
                        'original_line' => $line
                    ];
                    Log::info('Found course (Pattern 1)', ['code' => $code, 'line' => $line]);
                    continue;
                }
                
                // Pattern 2: "IT 411 Capstone Project 2" - space between code and number, space before title
                if (preg_match('/^([A-Z]{2,4})\s+(\d{3})\s+([A-Za-z].*)$/', $line, $matches)) {
                    $code = $matches[1] . ' ' . $matches[2];
                    $courses[] = [
                        'code' => $code,
                        'original_line' => $line
                    ];
                    Log::info('Found course (Pattern 2)', ['code' => $code, 'line' => $line]);
                    continue;
                }
                
                // Pattern 3: "BAT405Analytics Application" - no spaces at all
                if (preg_match('/^([A-Z]{2,4})(\d{3})([A-Za-z].*)$/', $line, $matches)) {
                    $code = $matches[1] . ' ' . $matches[2];
                    $courses[] = [
                        'code' => $code,
                        'original_line' => $line
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
                        'code' => $code,
                        'original_line' => $line
                    ];
                    Log::info('Found course (Pattern 4)', ['code' => $code, 'raw' => $rawCode, 'line' => $line]);
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
            'courses_found' => array_keys($uniqueCourses)
        ]);
        
        return array_values($uniqueCourses);
    }

    private function evaluateGraduationStatus(StudentManage $student, array $corCourses): array
    {
        try {
            // Get student's curriculum ID
            $curriculumId = $student->curriculum_id;
            if (!$curriculumId) {
                return [
                    'graduation_status' => 'NOT GRADUATING', // Simplified
                    'remarks' => 'NOT GRADUATING', // Simplified
                    'matched_courses' => [],
                    'missing_courses' => [],
                    'total_curriculum_courses' => 0,
                    'total_matched' => 0,
                    'total_missing' => 0
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
            $studentGrades = \App\Models\StudentGrade::where('Student_id', $student->Student_id)
                ->select('course_code', 'grade', 'subject_id')
                ->get();

            // Get all courses from student's curriculum
            $curriculumCourses = \App\Models\Curriculumsubject::where('curriculum_id', $curriculumId)
                ->select('subject_id', 'Code', 'Course_Title', 'year_level', 'semester', 'track', 'units')
                ->orderBy('year_level')
                ->orderBy('semester')
                ->get();

            if ($curriculumCourses->isEmpty()) {
                return [
                    'graduation_status' => 'NOT GRADUATING', // Simplified
                    'remarks' => 'NOT GRADUATING', // Simplified
                    'matched_courses' => [],
                    'missing_courses' => [],
                    'total_curriculum_courses' => 0,
                    'total_matched' => 0,
                    'total_missing' => 0
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
                    'source' => 'GRADE_HISTORY',
                    'grade' => $grade->grade,
                    'original_code' => $grade->course_code
                ];
            }

            // 2. Add courses from COR (currently enrolled)
            foreach ($corCourses as $corCourse) {
                $normalizedCode = $this->normalizeCourseCode($corCourse['code']);
                $allTakenCourses[$normalizedCode] = [
                    'source' => 'COR',
                    'grade' => 'CURRENTLY ENROLLED',
                    'original_code' => $corCourse['code']
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
                        'code' => $course->Code,
                        'title' => $course->Course_Title,
                        'year_level' => $course->year_level,
                        'semester' => $course->semester,
                        'track' => $course->track,
                        'units' => $course->units,
                        'status' => 'COMPLETED',
                        'source' => $takenInfo['source'],
                        'grade' => $takenInfo['grade']
                    ];
                } else {
                    // Course is missing
                    $missingCourses[] = [
                        'code' => $course->Code,
                        'title' => $course->Course_Title,
                        'year_level' => $course->year_level,
                        'semester' => $course->semester,
                        'track' => $course->track,
                        'units' => $course->units,
                        'status' => 'MISSING'
                    ];
                }
            }

            // SIMPLIFIED: Determine graduation status - just "GRADUATING" or "NOT GRADUATING"
            $graduationStatus = 'NOT GRADUATING';
            $remarks = 'NOT GRADUATING';
            $canApplyForGraduation = false;
            $canApplyForLatin = false;

            if (empty($missingCourses)) {
                // All courses completed - ready to graduate
                $graduationStatus = 'GRADUATING';
                $remarks = 'GRADUATING';
                $canApplyForGraduation = true;
                $canApplyForLatin = true;
            } else {
                // Check if student is candidate for graduation
                $missingFourthYearSecond = array_filter($missingCourses, function($course) {
                    return str_contains($course['year_level'], 'FOURTH') && 
                        str_contains($course['semester'], 'SECOND');
                });

                $missingOther = array_filter($missingCourses, function($course) {
                    return !(str_contains($course['year_level'], 'FOURTH') && 
                            str_contains($course['semester'], 'SECOND'));
                });

                // If only missing FOURTH YEAR, SECOND SEMESTER courses (like internship)
                if (empty($missingOther) && count($missingFourthYearSecond) > 0) {
                    $graduationStatus = 'GRADUATING'; // Simplified - candidate is still GRADUATING
                    $remarks = 'GRADUATING';
                    $canApplyForGraduation = true;
                    $canApplyForLatin = false; // Cannot apply for Latin honors until all courses have grades
                } else {
                    // Missing courses from previous years/semesters
                    $graduationStatus = 'NOT GRADUATING';
                    $remarks = 'NOT GRADUATING';
                    $canApplyForGraduation = false;
                    $canApplyForLatin = false;
                }
            }

            Log::info('Graduation evaluation result', [
                'student_id' => $student->Student_id,
                'graduation_status' => $graduationStatus,
                'missing_count' => count($missingCourses),
                'missing_courses' => array_column($missingCourses, 'code'),
                'can_apply_for_graduation' => $canApplyForGraduation,
                'can_apply_for_latin' => $canApplyForLatin
            ]);

            return [
                'graduation_status' => $graduationStatus, // Will be "GRADUATING" or "NOT GRADUATING"
                'remarks' => $remarks, // Will be "GRADUATING" or "NOT GRADUATING"
                'matched_courses' => $matchedCourses,
                'missing_courses' => $missingCourses,
                'total_curriculum_courses' => $requiredCourses->count(),
                'total_matched' => count($matchedCourses),
                'total_missing' => count($missingCourses),
                'completion_percentage' => $requiredCourses->count() > 0 ? 
                    round((count($matchedCourses) / $requiredCourses->count()) * 100, 2) : 0,
                'student_track' => $studentTrack,
                'can_apply_for_graduation' => $canApplyForGraduation,
                'can_apply_for_latin' => $canApplyForLatin,
                'cor_courses_count' => count($corCourses),
                'grade_courses_count' => $studentGrades->count(),
                'combined_courses_count' => count($allTakenCourses)
            ];

        } catch (\Exception $e) {
            Log::error('Graduation evaluation error', [
                'student_id' => $student->Student_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'graduation_status' => 'NOT GRADUATING', // Simplified
                'remarks' => 'NOT GRADUATING', // Simplified
                'matched_courses' => [],
                'missing_courses' => [],
                'total_curriculum_courses' => 0,
                'total_matched' => 0,
                'total_missing' => 0,
                'can_apply_for_graduation' => false,
                'can_apply_for_latin' => false
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

    /**
     * Check if two course codes match, handling variations
     */
    private function areCourseCodesMatching(string $code1, string $code2): bool
    {
        $normalized1 = $this->normalizeCourseCode($code1);
        $normalized2 = $this->normalizeCourseCode($code2);
        
        return $normalized1 === $normalized2;
    }
    /**
     * Save graduation evaluation to graduation requirements
     */
    private function saveGraduationEvaluation(StudentManage $student, array $evaluation): void
    {
        try {
            $graduationForm = GraduationForm::where('Student_id', $student->Student_id)->first();
            
            if ($graduationForm) {
                GraduationRequirement::updateOrCreate(
                    ['GraduationForm_id' => $graduationForm->GraduationForm_id],
                    ['remarks' => $evaluation['remarks']]
                );
                
                Log::info('Graduation evaluation saved', [
                    'student_id' => $student->Student_id,
                    'graduation_status' => $evaluation['graduation_status'],
                    'remarks' => $evaluation['remarks']
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to save graduation evaluation: ' . $e->getMessage());
        }
    }

    /**
     * Get graduation evaluation results (for frontend display)
     */
    public function getGraduationEvaluation(Request $request)
    {
        try {
            $loginId = optional($request->user())->Login_id
                ?? session('login_id')
                ?? session('Login_id');

            $student = $loginId ? StudentManage::where('Login_id', $loginId)->first() : null;
            if (!$student) {
                return response()->json(['ok' => false, 'message' => 'Student not found'], 404);
            }

            // Check if we have a recent evaluation
            $graduationForm = GraduationForm::where('Student_id', $student->Student_id)->first();
            
            if ($graduationForm) {
                $graduationReq = GraduationRequirement::where('GraduationForm_id', $graduationForm->GraduationForm_id)->first();
                
                if ($graduationReq && $graduationReq->remarks) {
                    return response()->json([
                        'ok' => true,
                        'has_evaluation' => true,
                        'remarks' => $graduationReq->remarks,
                        'evaluation_time' => $graduationReq->updated_at ?? $graduationReq->created_at
                    ]);
                }
            }

            return response()->json([
                'ok' => true,
                'has_evaluation' => false,
                'message' => 'No graduation evaluation found. Please upload COR and run evaluation.'
            ]);

        } catch (\Exception $e) {
            Log::error('Get graduation evaluation error: ' . $e->getMessage());
            return response()->json(['ok' => false, 'message' => 'Failed to get evaluation'], 500);
        }
    }

    /* ----------------------------------------------------------------------
     |  COG UPLOAD
     * --------------------------------------------------------------------*/

    /**
     * COG upload -> extract ALL plain text and save verbatim to cog_extracted.txt
     * PLUS: copy PDF to public storage; actual DB link to graduation_requirements
     * is done in saveFields() once GraduationForm exists.
     */
    public function uploadCog(Request $request)
    {
        $field = $request->hasFile('grades_pdf') ? 'grades_pdf'
            : ($request->hasFile('pdf') ? 'pdf' : 'cog');

        $request->validate([
            $field => ['required', 'file', 'mimetypes:application/pdf', 'max:51200'],
        ], ['mimetypes' => 'The file must be a PDF.']);

        File::ensureDirectoryExists($this->cogDir, 0755, true);

        $pdfPath = rtrim($this->cogDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'cog_uploaded.pdf';
        $txtPath = rtrim($this->cogDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'cog_extracted.txt';

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file($field);
        @unlink($pdfPath);
        $file->move($this->cogDir, 'cog_uploaded.pdf');

        // Extract text
        $allText = $this->extractFullTextPreferLayout($pdfPath);
        $allText = rtrim($allText, "\r\n");
        if ($allText === '') {
            $allText = "/* Extraction returned empty output. Confirm Poppler (pdftotext) is installed and try another PDF. */";
        }
        @File::put($txtPath, $allText);

        $pages = $this->countPagesPdfinfo($pdfPath) ?? $this->countPagesSmalot($pdfPath);

        // Copy to public for this student (deterministic path)
        $loginId   = optional($request->user())->Login_id
            ?? session('login_id')
            ?? session('Login_id');

        $studentId = session('Student_id');
        if (!$studentId && $loginId) {
            $student = StudentManage::where('Login_id', $loginId)->first();
            $studentId = $student->Student_id ?? null;
        }

        $publicUrl = null;

        if ($studentId) {
            $relative  = 'graduation/cog/cog_' . $studentId . '.pdf';
            $fullPath  = storage_path('app/public/' . $relative);
            @mkdir(dirname($fullPath), 0775, true);
            @copy($pdfPath, $fullPath);

            $publicUrl = Storage::url($relative); // "/storage/graduation/cog/cog_80.pdf"
        }

        return response()->json([
            'ok'               => true,
            'page_count'       => $pages,
            'grades_pdf_path'  => $pdfPath,
            'grades_text_path' => $txtPath,
            'grades_pdf_url'   => $publicUrl,
            'cog_pdf_path'     => $pdfPath,
            'pdf_path'         => $pdfPath,
            'txt_path'         => $txtPath,
        ]);
    }

    /* ----------------------------------------------------------------------
     |  CURRICULUM SUBJECTS
     * --------------------------------------------------------------------*/
    public function curriculumSubjects(Request $request): JsonResponse
    {
        $loginId = optional($request->user())->Login_id
            ?? session('login_id')
            ?? session('Login_id');

        $student = $loginId ? StudentManage::where('Login_id', $loginId)->first() : null;

        $curriculumId = $request->query('curriculum_id');
        if (!$curriculumId && $student) {
            $curriculumId = $student->Curriculum_id ?? $student->curriculum_id ?? null;
        }

        $q = DB::table('curriculum_subjects');
        if ($curriculumId) {
            $q->where('curriculum_id', $curriculumId);
        } elseif ($student && isset($student->Program)) {
            $q->where(function ($sub) use ($student) {
                $sub->where('program', $student->Program)
                    ->orWhere('program_code', $student->Program)
                    ->orWhere('program_name', $student->Program);
            });
        }

        $rows = $q->select(['id', 'curriculum_id',
            DB::raw("COALESCE(course_title, Course_Title, title, Title, name, Name) AS course_title"),
        ])->get();

        return response()->json(['ok' => true, 'data' => $rows]);
    }

    /* ----------------------------------------------------------------------
     |  Helpers — full name / pdftotext / bbox / fixed-width render
     * --------------------------------------------------------------------*/
    // Build full name: "FIRST M. LAST" (all caps)
    private function formattedStudentName(StudentManage $student): string
    {
        $first  = trim($student->First_name  ?? '');
        $middle = trim($student->Middle_name ?? '');
        $last   = trim($student->Last_name   ?? '');

        $middleInitial = '';
        if ($middle !== '') {
            $middleInitial = mb_strtoupper(mb_substr($middle, 0, 1, 'UTF-8'), 'UTF-8') . '.';
        }

        $full = trim($first . ' ' . $middleInitial . ' ' . $last);
        $full = preg_replace('/\s+/', ' ', $full);

        return mb_strtoupper($full, 'UTF-8');   // 🔹 CAPSLOCK
    }

    /**
     * From the COR text, try to extract a scholarship line with proper year detection
     */
    private function extractScholarshipFromCorText(string $text): ?string
    {
        if (trim($text) === '') {
            return null;
        }

        $lines = preg_split('/\r\n|\r|\n/', $text);
        $lines = array_map('trim', $lines);
        $count = count($lines);

        // 1) Pattern like:
        // Scholarship/s:
        // Higher Education Support Program : Free Tuition 2024
        for ($i = 0; $i < $count; $i++) {
            if (stripos($lines[$i], 'Scholarship/s') !== false) {
                // Look at the next non-empty line
                for ($j = $i + 1; $j < $count; $j++) {
                    $l = trim($lines[$j]);
                    if ($l === '') {
                        continue;
                    }

                    // Stop if this looks like another section header
                    if (preg_match('/^(ASSESSMENT|DISCOUNT|TOTAL\b|\(\*\)|Tuition Fee|Assessment Discount)/i', $l)) {
                        break;
                    }

                    // Remove trailing markers like " (*) Discount..." if merged
                    $parts = preg_split('/\(\*\)|Tuition Fee Discount|Assessment Discount|ASSESSMENT\b/i', $l);
                    $clean = trim($parts[0] ?? '');

                    // Extract year from scholarship line
                    $scholarshipWithYear = $this->extractScholarshipWithYear($clean);
                    return $scholarshipWithYear !== '' ? $scholarshipWithYear : $clean;
                }
            }
        }

        // 2) Fallback: single-line "Scholarship/s: <value>"
        foreach ($lines as $l) {
            if (preg_match('/Scholarship\/s:\s*(.+)$/i', $l, $m)) {
                $val = trim($m[1]);
                if ($val !== '') {
                    $scholarshipWithYear = $this->extractScholarshipWithYear($val);
                    return $scholarshipWithYear !== '' ? $scholarshipWithYear : $val;
                }
            }
        }

        return null;
    }

    /** Extract scholarship with proper year detection */
    private function extractScholarshipWithYear(string $scholarshipLine): string
    {
        $cleanLine = trim($scholarshipLine);
        
        // If the line already contains a 4-digit year, return as is
        if (preg_match('/\b\d{4}\b/', $cleanLine)) {
            return $cleanLine;
        }
        
        // If it's "Free Tuition" without year, add current year
        if (stripos($cleanLine, 'Free Tuition') !== false) {
            // Extract the actual year from the COR context if possible
            // For now, use current year as fallback
            $currentYear = date('Y');
            return $cleanLine . ' ' . $currentYear;
        }
        
        return $cleanLine;
    }

    private function extractWithPdftotext(string $absolutePdfPath, string $mode = 'layout'): ?string
    {
        $bin = $this->resolvePdftotext();
        if (!$bin || !file_exists($bin)) {
            return null;
        }

        $tmpOut = tempnam(sys_get_temp_dir(), 'pdftxt_') . '.txt';
        @unlink($tmpOut);

        $args = [$bin, '-nopgbrk', '-enc', 'UTF-8', '-q'];
        if ($mode === 'layout') {
            $args = array_merge($args, ['-layout', '-fixed', '9']);
        } else {
            $args[] = '-raw';
        }
        $args[] = $absolutePdfPath;
        $args[] = $tmpOut;

        $p = new Process($args, null, null, null, 60);
        try {
            $p->run();
            if (!$p->isSuccessful() || !file_exists($tmpOut)) {
                return null;
            }
            $txt = (string) @file_get_contents($tmpOut);
            @unlink($tmpOut);
            $txt = str_replace(["\r\n", "\r"], "\n", $txt);
            return trim($txt);
        } catch (\Throwable $e) {
            @unlink($tmpOut);
            return null;
        }
    }

    private function extractWithPdftotextBbox(string $absolutePdfPath): ?string
    {
        $bin = $this->resolvePdftotext();
        if (!$bin || !file_exists($bin)) {
            return null;
        }

        $tmpOut = tempnam(sys_get_temp_dir(), 'pdftbx_') . '.xml';
        @unlink($tmpOut);

        $args = [$bin, '-bbox-layout', '-enc', 'UTF-8', '-q', $absolutePdfPath, $tmpOut];

        $p = new Process($args, null, null, null, 60);
        try {
            $p->run();
            if (!$p->isSuccessful() || !file_exists($tmpOut)) {
                return null;
            }
            $xml = (string) @file_get_contents($tmpOut);
            @unlink($tmpOut);
            $xml = trim($xml);
            return $xml !== '' ? $xml : null;
        } catch (\Throwable $e) {
            @unlink($tmpOut);
            return null;
        }
    }

    /** Prefer -layout, fallback to -raw, fallback to Smalot; return raw text */
    private function extractFullTextPreferLayout(string $absolutePdfPath): string
    {
        $text = $this->extractWithPdftotext($absolutePdfPath, 'layout');
        if ($text === null || $text === '') {
            $text = $this->extractWithPdftotext($absolutePdfPath, 'raw');
        }
        if ($text === null || $text === '') {
            $text = $this->extractWithSmalot($absolutePdfPath);
        }
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        return rtrim($text, "\n");
    }

    /** Parse grades from pdftotext -bbox-layout XML */
    private function parseGradesFromBboxXml(string $xml): array
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        if (!$dom->loadXML($xml, LIBXML_NOERROR | LIBXML_NOWARNING)) {
            return ['meta' => [], 'program' => null, 'sections' => []];
        }
        $xp = new \DOMXPath($dom);

        $words = [];
        foreach ($xp->query('//word') as $w) {
            /** @var \DOMElement $w */
            $t = trim($w->textContent ?? '');
            if ($t === '') {
                continue;
            }
            $x1 = (float) $w->getAttribute('xMin');
            $x2 = (float) $w->getAttribute('xMax');
            $y1 = (float) $w->getAttribute('yMin');
            $y2 = (float) $w->getAttribute('yMax');
            $words[] = [
                't'  => $t,
                'x1' => $x1,
                'x2' => $x2,
                'y1' => $y1,
                'y2' => $y2,
                'yc' => ($y1 + $y2) / 2.0,
                'xc' => ($x1 + $x2) / 2.0,
            ];
        }
        if (!$words) {
            return ['meta' => [], 'program' => null, 'sections' => []];
        }

        usort($words, fn($a, $b) => $a['yc'] <=> $b['yc'] ?: $a['x1'] <=> $b['x1']);
        $lines  = [];
        $buf    = [];
        $lastY  = null;
        $tol    = 2.0;

        foreach ($words as $w) {
            if ($lastY === null || abs($w['yc'] - $lastY) <= $tol) {
                $buf[] = $w;
                $lastY = $lastY === null ? $w['yc'] : (($lastY * (count($buf) - 1) + $w['yc']) / count($buf));
            } else {
                $lines[] = $buf;
                $buf     = [$w];
                $lastY   = $w['yc'];
            }
        }
        if ($buf) {
            $lines[] = $buf;
        }

        $joinText  = fn($arr) => trim(preg_replace('/\s+/', ' ', implode(' ', array_map(fn($w) => $w['t'], $arr))));
        $isSection = fn($s) => (bool) preg_match('/^(SUMMER AY|FIRST SEMESTER AY|SECOND SEMESTER AY)\s+\d{4}-\d{4}\s*$/i', $s);
        $isHeader  = fn($s) => (bool) preg_match('/\bCode\b.*\bDescription\b.*\bCredits\b.*\bGrade\b.*\bInstructor\b/i', $s);
        $isTotal   = fn($s) => stripos($s, 'Total Subjects') === 0;
        $isCode    = fn(string $t) => (bool) preg_match('/^[A-Z]{2,}\s*\d{2,3}$/', trim($t));

        $meta     = [];
        $program  = null;
        $sections = [];
        $cur      = null;
        $cuts     = null;

        $deriveCuts = function (array $line) {
            $findWord = function (array $line, string $needle): ?array {
                foreach ($line as $w) {
                    if (preg_match('/^' . preg_quote($needle, '/') . '$/i', $w['t'])) {
                        return $w;
                    }
                }
                foreach ($line as $w) {
                    if (stripos($w['t'], $needle) !== false) {
                        return $w;
                    }
                }
                return null;
            };
            $wCode = $findWord($line, 'Code');
            $wDesc = $findWord($line, 'Description');
            $wCred = $findWord($line, 'Credits');
            $wGrad = $findWord($line, 'Grade');
            $wInst = $findWord($line, 'Instructor');
            if (!$wCode || !$wDesc || !$wCred || !$wGrad || !$wInst) {
                return null;
            }

            $mid        = fn($L, $R) => ($L['x2'] + $R['x1']) / 2.0;
            $leftGuard  = min($wCode['x1'], $wDesc['x1'], $wCred['x1']) - 2.0;
            $c1         = $mid($wCode, $wDesc);
            $c2         = $mid($wDesc, $wCred);
            $c3         = $mid($wCred, $wGrad);
            $c4         = $mid($wGrad, $wInst);
            $rightGuard = max($wCode['x2'], $wDesc['x2'], $wCred['x2'], $wGrad['x2'], $wInst['x2']) + 2.0;

            return [
                ['k' => 'code', 's' => $leftGuard, 'e' => $c1],
                ['k' => 'desc', 's' => $c1,        'e' => $c2],
                ['k' => 'cr',   's' => $c2,        'e' => $c3],
                ['k' => 'gr',   's' => $c3,        'e' => $c4],
                ['k' => 'inst', 's' => $c4,        'e' => $rightGuard],
            ];
        };

        $stitchHyphen = function (string $a, string $b): string {
            $a = rtrim($a);
            $b = ltrim($b);
            if ($a === '') {
                return $b;
            }
            if (str_ends_with($a, '-')) {
                return substr($a, 0, -1) . $b;
            }
            return $a . ' ' . $b;
        };

        foreach ($lines as $line) {
            usort($line, fn($a, $b) => $a['x1'] <=> $b['x1']);
            $plain = $joinText($line);
            if ($plain === '') {
                continue;
            }

            if (preg_match('/^(SRCODE|FULLNAME)\s*:/i', $plain)) {
                $meta[] = $plain;
                continue;
            }
            if ($program === null && preg_match('/^BS\s+.+/i', $plain)) {
                $program = $plain;
                continue;
            }

            if ($isSection($plain)) {
                if ($cur) {
                    $sections[] = $cur;
                }
                $cur  = ['title' => $plain, 'rows' => [], 'total' => null];
                $cuts = null;
                continue;
            }

            if ($cur && !$cuts && $isHeader($plain)) {
                $cuts = $deriveCuts($line);
                continue;
            }

            if ($cur && $cuts) {
                if ($isTotal($plain)) {
                    $cur['total'] = preg_replace('/\s+GWA\s*:\s*/', '     |        GWA : ', $plain);
                    continue;
                }

                $cols = ['code' => '', 'desc' => '', 'cr' => '', 'gr' => '', 'inst' => ''];
                foreach ($line as $w) {
                    foreach ($cuts as $c) {
                        if ($w['xc'] >= $c['s'] && $w['xc'] < $c['e']) {
                            $cols[$c['k']] .= ($cols[$c['k']] ? ' ' : '') . $w['t'];
                            break;
                        }
                    }
                }
                foreach ($cols as $k => $v) {
                    $cols[$k] = trim(preg_replace('/\s+/', ' ', $v));
                }

                if ($cols['code'] === '' && $cols['desc'] !== '') {
                    if (!empty($cur['rows'])) {
                        $last                        = count($cur['rows']) - 1;
                        $cur['rows'][$last]['desc']  = $stitchHyphen($cur['rows'][$last]['desc'], $cols['desc']);
                        foreach (['cr', 'gr', 'inst'] as $k) {
                            if ($cols[$k] !== '') {
                                $cur['rows'][$last][$k] = trim(($cur['rows'][$last][$k] ?? '') . ' ' . $cols[$k]);
                            }
                        }
                        continue;
                    }
                }

                if ($cols['code'] !== '' && !$isCode($cols['code'])) {
                    if (preg_match('/^([A-Z]{2,}\s*\d{2})\s*$/', $cols['code']) && preg_match('/^(\d)\b(.*)$/', $cols['desc'], $m)) {
                        $cols['code'] = trim($cols['code'] . ' ' . $m[1]);
                        $cols['desc'] = trim($m[2]);
                    }
                }

                $cols = $this->normalizeRow($cols);

                if ($cols['code'] !== '' || $cols['desc'] !== '' || $cols['cr'] !== '' || $cols['gr'] !== '' || $cols['inst'] !== '') {
                    $cur['rows'][] = $cols;
                }
            }
        }
        if ($cur) {
            $sections[] = $cur;
        }

        return ['meta' => $meta, 'program' => $program, 'sections' => $sections];
    }

    /** Normalize a single row: fix spills and tidy tokens */
    private function normalizeRow(array $r): array
    {
        $r['code'] = trim($r['code']);
        $r['desc'] = trim($r['desc']);
        $r['cr']   = trim($r['cr']);
        $r['gr']   = trim($r['gr']);
        $r['inst'] = trim($r['inst']);

        if ($r['cr'] === '' && preg_match('/\b([0-9])\s*$/', $r['desc'], $m)) {
            $r['cr']   = $m[1];
            $r['desc'] = trim(preg_replace('/\b[0-9]\s*$/', '', $r['desc']));
        }

        if ($r['gr'] !== '' && preg_match('/^(INC)(?:\s+([A-Z][A-Z\- ,\.]+.*))$/', $r['gr'], $m)) {
            $r['gr']   = 'INC';
            $r['inst'] = trim($m[2] . ' ' . $r['inst']);
        }

        if ($r['gr'] === '' && preg_match('/\b(1\.\d{2}|2\.\d{2}|3\.00|INC(?:\/\d\.\d{2})?)\b/i', $r['desc'], $m)) {
            $r['gr']   = strtoupper($m[1]);
            $r['desc'] = trim(str_replace($m[1], '', $r['desc']));
        }

        if (preg_match('/^(1\.\d{2}|2\.\d{2}|3\.00|INC)(?:\s+([A-Z][A-Z].*))$/', $r['gr'], $m)) {
            $r['gr']   = strtoupper($m[1]);
            $r['inst'] = trim($m[2] . ' ' . $r['inst']);
        }

        foreach (['code', 'desc', 'cr', 'gr', 'inst'] as $k) {
            $r[$k] = trim(preg_replace('/\s+/', ' ', $r[$k]));
        }
        return $r;
    }

    private function renderParsedToFixedWidth(array $parsed): string
    {
        $CW = 10;
        $DW = 60;
        $CR = 7;
        $GR = 7;
        $IN = 25;

        $pad = function ($t, $w) {
            $tr  = mb_strimwidth($t, 0, $w, '', 'UTF-8');
            $len = mb_strlen($tr, 'UTF-8');
            return $tr . str_repeat(' ', max(0, $w - $len));
        };
        $center = function ($t, $w = 100) {
            $t   = trim($t);
            $len = mb_strlen($t, 'UTF-8');
            $p   = max(0, intdiv($w - $len, 2));
            return str_repeat(' ', $p) . $t;
        };

        $out   = [];
        $out[] = $center('REPORT OF GRADES');
        foreach (($parsed['meta'] ?? []) as $m) {
            $out[] = $m;
        }
        if (!empty($parsed['program'])) {
            $out[] = '';
            $out[] = $center($parsed['program']);
        }

        foreach (($parsed['sections'] ?? []) as $sec) {
            $out[] = '';
            $out[] = $center($sec['title']);
            $out[] = '';
            $out[] = $pad('Code', $CW) . ' | ' . $pad('Description', $DW) . ' | ' . $pad('Credits', $CR) . ' | ' . $pad('Grade', $GR) . ' | ' . $pad('Instructor', $IN);
            foreach (($sec['rows'] ?? []) as $r) {
                $out[] = $pad($r['code'] ?? '', $CW) . ' | '
                    . $pad($r['desc'] ?? '', $DW) . ' | '
                    . $pad($r['cr']   ?? '', $CR) . ' | '
                    . $pad($r['gr']   ?? '', $GR) . ' | '
                    . $pad($r['inst'] ?? '', $IN);
            }
            if (!empty($sec['total'])) {
                $out[] = '';
                $out[] = $center($sec['total']);
            }
        }
        return trim(implode("\n", $out));
    }

    /**
     * Debug COR detection and student update
     */
    public function debugCorDetection(Request $request)
    {
        $loginId = optional($request->user())->Login_id
            ?? session('login_id')
            ?? session('Login_id');

        $student = $loginId ? StudentManage::where('Login_id', $loginId)->first() : null;
        
        if (!$student) {
            return response()->json(['error' => 'Student not found', 'login_id' => $loginId]);
        }

        // Check current student data
        $currentData = [
            'student_id' => $student->Student_id,
            'current_year' => $student->Year,
            'current_academic_year' => $student->Academic_year,
            'login_id' => $student->Login_id
        ];

        // Check if COR file exists and read it
        $corTextPath = $this->corDir . '/cor_output.txt';
        $corExists = file_exists($corTextPath);
        
        if ($corExists) {
            $corText = file_get_contents($corTextPath);
            $academicInfo = $this->extractAcademicInfoFromCor($corText);
            
            // Test the update
            $updateResult = $this->updateStudentAcademicInfo($student, $academicInfo);
            
            // Refresh student data
            $student->refresh();
            
            return response()->json([
                'current_student_data' => $currentData,
                'cor_file_exists' => $corExists,
                'detected_academic_info' => $academicInfo,
                'update_result' => $updateResult,
                'student_data_after_update' => [
                    'Year' => $student->Year,
                    'Academic_year' => $student->Academic_year
                ],
                'cor_text_sample' => substr($corText, 0, 1000) // First 1000 chars for debugging
            ]);
        }

        return response()->json([
            'current_student_data' => $currentData,
            'cor_file_exists' => $corExists,
            'message' => 'No COR file found. Please upload a COR first.'
        ]);
    }
    /* ----------------------------------------------------------------------
     |  Misc helper
     * --------------------------------------------------------------------*/
    private static function toDateYmd(?string $raw): ?string
    {
        if (!$raw) {
            return null;
        }
        foreach (['Y-m-d', 'm/d/Y', 'm-d-Y', 'd/m/Y', 'd-m-Y', 'M d, Y', 'd M Y', 'Y/m/d'] as $fmt) {
            try {
                $c = Carbon::createFromFormat($fmt, trim($raw));
                if ($c !== false) {
                    return $c->format('Y-m-d');
                }
            } catch (\Throwable) {
            }
        }
        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /** Resolve the current/most-recent Graduation_id for a student */
    private function currentGraduationIdFor(int $studentId): ?int
    {
        $row = DB::table('graduation_form')
            ->where('Student_id', $studentId)
            ->orderByDesc('GraduationForm_id')
            ->first();

        return $row->GraduationForm_id ?? null;
    }

    /* ----------------------------------------------------------------------
     |  Binary resolvers + fallbacks (Poppler/Xpdf + Smalot)
     * --------------------------------------------------------------------*/

    /** Try to find Poppler's pdftotext. Prefer .env, then common Windows paths. */
    private function resolvePdftotext(): ?string
    {
        $env = env('PDFTOTEXT_PATH');
        if ($env && is_file($env)) {
            return $env;
        }

        $candidates = [
            'C:\Program Files\poppler-24.07.0\Library\bin\pdftotext.exe',
            'C:\Program Files\poppler\bin\pdftotext.exe',
            'C:\Program Files (x86)\poppler\bin\pdftotext.exe',
            'C:\Program Files\Xpdf\pdftotext.exe',
            'C:\xpdf\pdftotext.exe',
        ];
        foreach ($candidates as $p) {
            if (is_file($p)) {
                return $p;
            }
        }

        return null;
    }

    /** Resolve pdfinfo (for page counts). Tries .env then neighbors of pdftotext. */
    private function resolvePdfinfo(): ?string
    {
        $env = env('PDFINFO_PATH');
        if ($env && is_file($env)) {
            return $env;
        }

        $txt = $this->resolvePdftotext();
        if ($txt) {
            $dir  = dirname($txt);
            $cand = $dir . DIRECTORY_SEPARATOR . 'pdfinfo.exe';
            if (is_file($cand)) {
                return $cand;
            }
        }

        $candidates = [
            'C:\Program Files\poppler-24.07.0\Library\bin\pdfinfo.exe',
            'C:\Program Files\poppler\bin\pdfinfo.exe',
            'C:\Program Files (x86)\poppler\bin\pdfinfo.exe',
            'C:\Program Files\Xpdf\pdfinfo.exe',
            'C:\xpdf\pdfinfo.exe',
        ];
        foreach ($candidates as $p) {
            if (is_file($p)) {
                return $p;
            }
        }
        return null;
    }

    /** Simple Smalot fallback (raw text). */
    private function extractWithSmalot(string $absolutePdfPath): string
    {
        try {
            $parser = new Parser();
            $pdf    = $parser->parseFile($absolutePdfPath);
            return trim(str_replace(["\r\n", "\r"], "\n", $pdf->getText() ?? ''));
        } catch (\Throwable $e) {
            \Log::warning('Smalot extract failed', ['err' => $e->getMessage()]);
            return '';
        }
    }

    /** Page count using pdfinfo (Poppler). */
    private function countPagesPdfinfo(string $absolutePdfPath): ?int
    {
        $bin = $this->resolvePdfinfo();
        if (!$bin) {
            return null;
        }

        try {
            $p = new Process([$bin, $absolutePdfPath]);
            $p->setTimeout(30);
            $p->run();
            if (!$p->isSuccessful()) {
                return null;
            }

            $out = $p->getOutput();
            if (preg_match('/^Pages:\s*(\d+)/mi', $out, $m)) {
                return (int) $m[1];
            }
            return null;
        } catch (\Throwable $e) {
            \Log::info('pdfinfo failed', ['err' => $e->getMessage()]);
            return null;
        }
    }

    /** Page count via Smalot when pdfinfo is unavailable. */
    private function countPagesSmalot(string $absolutePdfPath): ?int
    {
        try {
            $parser  = new Parser();
            $pdf     = $parser->parseFile($absolutePdfPath);
            $details = $pdf->getDetails();
            if (isset($details['Pages'])) {
                return (int) $details['Pages'];
            }
            $pages = $pdf->getPages();
            return $pages ? count($pages) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Remove obvious headers/footers and artifacts common in registrar PDFs. */
    private static function stripCogNoise(string $text): string
    {
        if ($text === null) {
            return '';
        }
        $t = preg_replace("/\r\n?/", "\n", (string) $text);

        $lines = array_values(array_filter(array_map('trim', explode("\n", $t)), function ($ln) {
            if ($ln === '') {
                return false;
            }
            if (preg_match('/^Page\s+\d+\s+of\s+\d+$/i', $ln)) {
                return false;
            }
            if (preg_match('/^(Registrar|Official Transcript|Unofficial Transcript)/i', $ln)) {
                return false;
            }
            if (preg_match('/^Generated on:\s*/i', $ln)) {
                return false;
            }
            return true;
        }));

        $t = preg_replace('/[ \t]+/', ' ', implode("\n", $lines));
        $t = preg_replace('/\n{3,}/', "\n\n", $t);
        return trim($t);
    }

    /** Render a crude fixed-width table when no bbox structure is available. */
    private static function prettyCogTable(string $clean): string
    {
        if ($clean === '') {
            return '';
        }

        $rows = [];
        foreach (explode("\n", $clean) as $ln) {
            $ln = trim($ln);
            if ($ln === '') {
                continue;
            }

            $parts = preg_split('/\s{2,}/', $ln);
            if (count($parts) >= 3) {
                $rows[] = $parts;
            }
        }

        if (!$rows) {
            return $clean;
        }

        $maxCols = 0;
        foreach ($rows as $r) {
            $maxCols = max($maxCols, count($r));
        }
        $widths = array_fill(0, $maxCols, 0);
        foreach ($rows as $r) {
            foreach ($r as $i => $cell) {
                $widths[$i] = max($widths[$i], mb_strlen($cell, 'UTF-8'));
            }
        }

        $pad = function ($t, $w) {
            $len = mb_strlen($t, 'UTF-8');
            return $t . str_repeat(' ', max(0, $w - $len));
        };

        $out = [];
        foreach ($rows as $r) {
            $line = '';
            foreach ($r as $i => $cell) {
                $line .= ($i ? ' | ' : '') . $pad($cell, $widths[$i]);
            }
            $out[] = $line;
        }
        return implode("\n", $out);
    }
}