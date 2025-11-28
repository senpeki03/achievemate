<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\StudentManage;
use App\Models\StudentCourse;
use App\Models\GraduationForm;
use App\Models\GraduationRequirement;
use App\Models\College;
use App\Models\Program;
use App\Models\Major;
use App\Models\UserDesignation;
use setasign\Fpdi\Fpdi;

class GraduationListController extends Controller
{
    /**
     * Hierarchy page:
     * level = college | program | major
     */
    public function index(Request $request)
    {
        $login = auth()->user();
        if (!$login) {
            abort(403, 'Unauthorized');
        }

        // ===== Registrar designation (campus-based) =====
        $designation = UserDesignation::with('campus')
            ->where('Login_id', $login->Login_id)
            ->first();

        if (!$designation) {
            abort(403, 'No designation found for this user.');
        }

        $userCampusId   = $designation->Campus_id;
        $userCampusName = optional($designation->campus)->Campus_name;

        // ===== Level + filters from query =====
        $level     = $request->query('level', 'college');  // college | program | major
        $campusId  = $request->query('campus_id', $userCampusId);
        $collegeId = $request->query('college_id');
        $programId = $request->query('program_id');

        // Safety: force campus to registrar campus
        $campusId = $userCampusId;

        // ===== Base query for Fourth-Year graduating =====
        $base = StudentCourse::from('student_course as sc')
            ->join('student_manage as sm', 'sm.Student_id', '=', 'sc.Student_id')
            ->join('graduation_form as gf', 'gf.Student_id', '=', 'sc.Student_id')
            ->join('graduation_requirements as gr', 'gr.GraduationForm_id', '=', 'gf.GraduationForm_id')
            ->where('sc.Campus_id', $campusId)
            ->where('sm.Year', 'FOURTH YEAR');

        // ===== Build rows depending on level =====
        $rows = collect();

        if ($level === 'college') {
            // Group actual graduating students by college
            $grouped = (clone $base)
                ->select('sc.College_id', 'sc.Student_id')
                ->get()
                ->groupBy('College_id');

            // Get ALL colleges in this campus
            $colleges = College::where('Campus_id', $campusId)
                ->orderBy('College_name')
                ->get();

            // Build rows for ALL colleges (0 count allowed)
            $rows = $colleges->map(function ($college) use ($grouped) {
                $items      = $grouped->get($college->College_id, collect());
                $studentIds = $items->pluck('Student_id')->unique();

                return (object) [
                    'id'    => $college->College_id,
                    'name'  => $college->College_name,
                    'total' => $studentIds->count(),   // 0 if no Fourth-Year graduating
                ];
            })->values();
        } elseif ($level === 'program') {
            if (!$collegeId) {
                return redirect()->route('registrar.graduationlist', ['level' => 'college']);
            }

            $grouped = (clone $base)
                ->where('sc.College_id', $collegeId)
                ->select('sc.Program_id', 'sc.Student_id')
                ->get()
                ->groupBy('Program_id');

            $programs = Program::where('Campus_id', $campusId)
                ->where('College_id', $collegeId)
                ->orderBy('Program_name')
                ->get()
                ->keyBy('Program_id');

            $rows = $grouped->map(function ($items, $pid) use ($programs) {
                $program = $programs->get($pid);
                if (!$program) return null;

                $studentIds = $items->pluck('Student_id')->unique();
                return (object) [
                    'id'    => $program->Program_id,
                    'name'  => $program->Program_name,
                    'total' => $studentIds->count(),
                ];
            })->filter()->values();
        } else { // level === 'major'
            if (!$collegeId || !$programId) {
                return redirect()->route('registrar.graduationlist', ['level' => 'college']);
            }

            $grouped = (clone $base)
                ->where('sc.College_id', $collegeId)
                ->where('sc.Program_id', $programId)
                ->select('sc.Major_id', 'sc.Student_id')
                ->get()
                ->groupBy('Major_id');   // may null = no major

            $majors = Major::where('Campus_id', $campusId)
                ->where('College_id', $collegeId)
                ->where('Program_id', $programId)
                ->orderBy('Major_name')
                ->get()
                ->keyBy('Major_id');

            $rows = $grouped->map(function ($items, $mid) use ($majors) {
                $midInt  = $mid === '' ? null : $mid;
                $major   = $midInt ? $majors->get($midInt) : null;
                $name    = $major ? $major->Major_name : 'No Major';

                $studentIds = $items->pluck('Student_id')->unique();

                return (object) [
                    'id'    => $midInt,    // can be null
                    'name'  => $name,
                    'total' => $studentIds->count(),
                ];
            })->values();
        }

        $title = 'Graduation List';

        return view('registrar.graduationlist', compact(
            'rows',
            'title',
            'userCampusId',
            'userCampusName',
            'level',
            'campusId',
            'collegeId',
            'programId'
        ));
    }

    /**
     * Detailed list of graduating students (FOURTH YEAR) per program/major.
     * Route: registrar/graduationlist/students
     */
    public function students(Request $request)
    {
        $login = auth()->user();
        if (!$login) {
            abort(403, 'Unauthorized');
        }

        $designation = UserDesignation::with('campus')
            ->where('Login_id', $login->Login_id)
            ->first();

        if (!$designation) {
            abort(403, 'No designation found for this user.');
        }

        $campusId = $designation->Campus_id;

        $collegeId = $request->query('college_id');
        $programId = $request->query('program_id');
        $majorId   = $request->query('major_id'); // 0 or empty = no major

        if (!$collegeId || !$programId) {
            return redirect()->route('registrar.graduationlist', ['level' => 'college']);
        }

        // ===== Header info =====
        $college = College::find($collegeId);
        $program = Program::find($programId);
        $major   = null;

        if ($majorId && (int)$majorId !== 0) {
            $major = Major::find($majorId);
        }

        // ===== Query graduating Fourth Year students =====
        $query = StudentCourse::from('student_course as sc')
            ->join('student_manage as sm', 'sm.Student_id', '=', 'sc.Student_id')
            ->join('graduation_form as gf', 'gf.Student_id', '=', 'sc.Student_id')
            ->leftJoin('graduation_requirements as gr', 'gr.GraduationForm_id', '=', 'gf.GraduationForm_id')
            ->where('sc.Campus_id', $campusId)
            ->where('sc.College_id', $collegeId)
            ->where('sc.Program_id', $programId)
            ->where('sm.Year', 'FOURTH YEAR');

        if ($majorId === '0' || $majorId === 0 || $majorId === null || $majorId === '') {
            $query->whereNull('sc.Major_id');
        } else {
            $query->where('sc.Major_id', $majorId);
        }

        $students = $query
            ->select(
                'sm.*',
                'sc.Student_id',
                'sc.Major_id',
                'gf.GraduationForm_id',
                'gr.GraduationReq_id',      // for Action button
                'gr.Approval_Sheet',
                'gr.Certificate_Library',
                'gr.Barangay_Clearance',
                'gr.Birth_Certificate',
                'gr.applicationform_grad',  // path to generated PDF
                'gr.reportofgrade_path',
                'gr.remarks'
            )
            ->orderBy('sm.Last_name')
            ->get();

        $title = 'Graduation List';

        return view('registrar.graduationlist_students', compact(
            'students',
            'college',
            'program',
            'major',
            'title'
        ));
    }

    /**
     * Called when registrar confirms "Evaluate" in the modal.
     * - DOES NOT save anything to DB
     * - Opens the student's applicationform_grad PDF
     * - Stamps the registrar's name into the PDF (overwrites same file)
     */
    public function evaluate(Request $request)
    {
        Log::info('Registrar.evaluate: start', [
            'payload' => $request->all(),
        ]);

        $request->validate([
            'graduation_req_id' => 'required|integer|exists:graduation_requirements,GraduationReq_id',
        ]);

        $login = auth()->user();
        if (!$login) {
            Log::warning('Registrar.evaluate: no auth user');
            abort(403, 'Unauthorized');
        }

        // Kunin designation + user profile para sa full_name
        $designation = UserDesignation::with('user')
            ->where('Login_id', $login->Login_id)
            ->first();

        if (!$designation || !$designation->user) {
            Log::warning('Registrar.evaluate: no designation/user for login', [
                'login_id' => $login->Login_id ?? null,
            ]);
            $registrarName = 'Registrar Staff';
        } else {
            $registrarName = $designation->user->full_name;
        }

        Log::info('Registrar.evaluate: resolved registrar name', [
            'login_id'      => $login->Login_id ?? null,
            'registrarName' => $registrarName,
        ]);

        // Get graduation requirement row to access PDF path
        $req = GraduationRequirement::findOrFail($request->graduation_req_id);

        $relativePath = $req->applicationform_grad; // e.g. "/storage/pdf_output/graduation_form_80.pdf"
        Log::info('Registrar.evaluate: graduation requirement record', [
            'GraduationReq_id'      => $req->GraduationReq_id,
            'applicationform_grad'  => $relativePath,
        ]);

        if (!$relativePath) {
            Log::warning('Registrar.evaluate: no applicationform_grad path');
            return response()->json([
                'success' => false,
                'message' => 'No application form PDF found for this student.',
            ], 422);
        }

        /**
         * Normalize the URL-style path to real storage path:
         *  - "/storage/pdf_output/graduation_form_80.pdf"
         *  - "storage/pdf_output/graduation_form_80.pdf"
         *  => storage_path("app/public/pdf_output/graduation_form_80.pdf")
         */
        $clean = ltrim($relativePath, '/');              // "storage/pdf_output/..."
        if (str_starts_with($clean, 'storage/')) {
            $clean = substr($clean, strlen('storage/')); // "pdf_output/..."
        }

        $pdfPath = storage_path('app/public/' . $clean);

        Log::info('Registrar.evaluate: resolved pdfPath', [
            'pdfPath'     => $pdfPath,
            'file_exists' => file_exists($pdfPath),
        ]);

        if (!file_exists($pdfPath)) {
            Log::error('Registrar.evaluate: PDF file does not exist', [
                'graduation_req_id' => $req->GraduationReq_id,
                'relativePath'      => $relativePath,
                'resolvedPath'      => $pdfPath,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Application form PDF file does not exist on the server.',
            ], 404);
        }

        // ==========================
        //  Stamp registrar name on PDF
        // ==========================
        try {
            Log::info('Registrar.evaluate: FPDI processing start', [
                'pdfPath' => $pdfPath,
            ]);

            $pdf = new Fpdi();

            $pageCount = $pdf->setSourceFile($pdfPath);
            Log::info('Registrar.evaluate: source file loaded', [
                'pageCount' => $pageCount,
            ]);

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $tplId = $pdf->importPage($pageNo);
                $size  = $pdf->getTemplateSize($tplId);

                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($tplId);

                // Assuming signature area is on page 1 – adjust coordinates if needed
                if ($pageNo === 1) {
                    $pdf->SetFont('Times', '', 12);
                    $pdf->SetTextColor(0, 0, 0);

                    // Adjust coordinates to match the "Registrar's Staff" line
                    $pdf->SetXY(160, 140); // tweak if kailangan
                    $pdf->Cell(60, 5, $registrarName, 0, 0, 'C');

                    Log::info('Registrar.evaluate: stamping name on page 1', [
                        'x'             => 160,
                        'y'             => 140,
                        'registrarName' => $registrarName,
                    ]);
                }
            }

            // dest = 'F' (file), name = $pdfPath
            $pdf->Output('F', $pdfPath);

            Log::info('Registrar.evaluate: FPDI output written', [
                'pdfPath' => $pdfPath,
            ]);
        } catch (\Throwable $e) {
            Log::error('Registrar.evaluate: PDF update failed', [
                'graduation_req_id' => $req->GraduationReq_id,
                'error'             => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update the PDF file.',
            ], 500);
        }

        Log::info('Registrar.evaluate: success', [
            'graduation_req_id' => $req->GraduationReq_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Student evaluated successfully. Registrar name inserted into PDF.',
        ]);
    }
}
