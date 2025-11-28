<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Route;
use setasign\Fpdi\Fpdi;

use App\Models\GraduationForm;
use App\Models\StudentManage;
use App\Models\StudentCourse;   // 🔹 NEW
use App\Models\College;         // 🔹 (optional, if you need it directly)
use App\Models\Program;         // 🔹 (optional)
use App\Models\Major;           // 🔹 (optional)

class LatinHonorsController extends Controller
{
    public function index()
    {
        // Render your Latin Honors page. Create resources/views/student/latin.blade.php
        return view('student.latin');
    }

    public function generate(Request $request)
    {
        $studentId = session('Student_id');
        if (!$studentId) {
            return $this->safeRedirectWith(
                'You are not logged in.',
                null
            );
        }

        // 1) Load Graduation Form (para makuha rin guardians, scholarship, etc.)
        $grad = GraduationForm::with(['student'])
            ->where('Student_id', $studentId)
            ->orderByDesc('GraduationForm_id')
            ->first();

        if (!$grad) {
            return $this->safeRedirectWith('Graduation form not found.');
        }

        $student = $grad->student;

        // 🔹 1A) MAIN SOURCE: student_course -> college / program / major
        $course = StudentCourse::with(['college', 'program', 'major'])
            ->where('Student_id', $studentId)
            ->orderByDesc('StudentCourse_id')
            ->first();

        $college = $course?->college;
        $program = $course?->program;
        $major   = $course?->major;

        // (Optional) fallback via curriculum chain kung wala talagang StudentCourse
        if (!$college || !$program || !$major) {
            $curriculum = $student->curriculum ?? null;
            if ($curriculum) {
                if (!$program && $curriculum->program ?? null) {
                    $program = $curriculum->program;
                }
                if (!$college && $curriculum->curriculumAy?->college ?? null) {
                    $college = $curriculum->curriculumAy->college;
                }
                // kung may relation ka rin sa Major sa curriculum_ay, pwede mong idagdag dito
            }
        }

        // 2) Resolve template path
        $template = storage_path('app/pdf_templates/BatStateU-FO-REG-09_Consent Form for the Evaluation of Academic Records_Rev. 03.pdf');
        if (!is_file($template)) {
            return $this->safeRedirectWith('Consent PDF template is missing.');
        }

        // 3) Prepare output path
        $outRel  = 'consent_forms/'.$studentId.'_'.now()->format('Ymd_His').'.pdf';
        $outAbs  = Storage::disk('public')->path($outRel);

        // 4) Build field values
        // campusName for campus checkbox section (default ARASOF - Nasugbu kung di ma-resolve)
        $campusName   = trim($college->College_name ?? 'ARASOF - Nasugbu');

        $lastName     = trim($student->Last_name ?? '');
        $firstName    = trim($student->First_name ?? '');
        $middleName   = trim($student->Middle_name ?? '');
        $ext          = trim($student->Ext ?? '');

        // 🔹 College / Program / Major from relations
        $collegeText  = trim($college->College_name ?? '');
        $programText  = trim($program->Program_name ?? '');
        $majorText    = trim($major->Major_name ?? '');

        $scholarship  = trim($grad->Scholarship_grant ?? '');
        $guardian1    = trim($grad->Guardian_1 ?? '');
        $guardian1No  = trim($grad->Guardian_1_Contact ?? '');
        $guardian2    = trim($grad->Guardian_2 ?? '');
        $guardian2No  = trim($grad->Guardian_2_Contact ?? '');

        // "Lastname, Firstname Middlename Ext"
        $studentNameLine = sprintf('%s, %s %s %s', $lastName, $firstName, $middleName, $ext);
        $studentNameLine = trim(preg_replace('/\s+/', ' ', $studentNameLine));

        // 5) Start FPDI and write text at fixed coordinates
        $pdf = new Fpdi('P', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->setSourceFile($template);
        $tpl = $pdf->importPage(1);
        $pdf->useTemplate($tpl, 0, 0, 215.9); // Letter width in mm

        // Font setup
        $pdf->SetAutoPageBreak(false);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetTextColor(0, 0, 0);

        // Helpers
        $write = function($x, $y, $text, $size=10, $style='') use ($pdf) {
            $pdf->SetFont('Helvetica', $style, $size);
            $pdf->SetXY($x, $y);
            $pdf->Cell(0, 5, iconv('UTF-8','windows-1252//TRANSLIT',$text), 0, 0);
        };
        $check = function($x, $y) use ($pdf) {
            $pdf->SetFont('ZapfDingbats','', 14);
            $pdf->SetXY($x, $y);
            $pdf->Cell(5,5, '4', 0, 0); // ✔
            $pdf->SetFont('Helvetica','',10);
        };

        // Coordinates map for campuses
        $campusToCoord = [
            'Pablo Borbon'      => [40, 42.5],
            'Alangilan'         => [90, 42.5],
            'ARASOF - Nasugbu'  => [145, 42.5],
            'JPLPC - Malvar'    => [40, 50.5],
            'Balayan'           => [90, 50.5],
            'Lipa'              => [145, 50.5],
            'Lobo'              => [40, 58.5],
            'Mabini'            => [90, 58.5],
            'Rosario'           => [145, 58.5],
            'Lemery'            => [190, 50.5],
            'San Juan'          => [190, 58.5],
        ];

        $picked = 'ARASOF - Nasugbu';
        foreach ($campusToCoord as $label => $_) {
            if (stripos($campusName, $label) !== false) {
                $picked = $label;
                break;
            }
        }

        // 🔹 kung gusto mong gamitin exact coord ng campus, uncomment:
        // [$cx, $cy] = $campusToCoord[$picked] ?? [100, 20];
        // $check($cx, $cy);

        // for now naka-check lang default box (pwede mong ayusin sa taas)
        $check(127, 36.5);

        // Name / college / program / etc.
        $write(60,  54.0, $lastName);
        $write(100, 54.0, $firstName);
        $write(150, 54.0, $middleName);
        $write(190, 54.0, $ext);

        // 🔹 College / Program / Major (now from StudentCourse relations)
        $write(50,  72.0, $collegeText);
        $write(50,  77.5, $programText);
        $write(50, 82.5, $majorText);

        $write(50, 90.0, $scholarship);

        $write(50,  97.0, $guardian1);
        $write(165, 97.0, $guardian1No);
        $write(50,  102.5, $guardian2);
        $write(165, 102.5, $guardian2No);

        // Consent paragraph checkbox
        $check(15, 110.0);

        // Signatures
        $dateNow = now()->format('M d, Y');
        $write(32,  140.0, $studentNameLine, 10, '');
        $write(48,  152.0, 'Date: '.$dateNow, 9, '');

        // Save
        @mkdir(dirname($outAbs), 0775, true);
        $pdf->Output($outAbs, 'F');

        $url = Storage::disk('public')->url($outRel);

        // Ajax? keep JSON for modal-based UX
        if ($request->ajax()) {
            return response()->json([
                'ok'   => true,
                'url'  => $url,
                'path' => $outRel,
            ]);
        }

        // Normal request → redirect to student.latin (safe guard)
        return $this->safeRedirectWith('Consent form generated successfully!', $url);
    }

    /**
     * Redirect helper that prefers student.latin, falls back gracefully.
     */
    private function safeRedirectWith(string $msg, ?string $url = null)
    {
        $target = Route::has('student.latin') ? 'student.latin'
                 : (Route::has('latin') ? 'latin' : null);

        $redirect = $target
            ? redirect()->route($target)
            : redirect('/student/portfolio');

        $redirect = $redirect->with('success', $msg);
        if ($url) {
            $redirect->with('pdf_url', $url);
        }

        return $redirect;
    }
}
