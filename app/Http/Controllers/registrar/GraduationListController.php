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
        if (!$login) abort(403, 'Unauthorized');

        $designation = UserDesignation::with('campus')
            ->where('Login_id', $login->Login_id)
            ->first();

        if (!$designation) abort(403, 'No designation found for this user.');

        $userCampusId   = $designation->Campus_id;
        $userCampusName = optional($designation->campus)->Campus_name;

        $level     = $request->query('level', 'college');
        $campusId  = $userCampusId;
        $collegeId = $request->query('college_id');
        $programId = $request->query('program_id');

        $base = StudentCourse::from('student_course as sc')
            ->join('student_manage as sm', 'sm.Student_id', '=', 'sc.Student_id')
            ->join('graduation_form as gf', 'gf.Student_id', '=', 'sc.Student_id')
            ->join('graduation_requirements as gr', 'gr.GraduationForm_id', '=', 'gf.GraduationForm_id')
            ->where('sc.Campus_id', $campusId)
            ->where('sm.Year', 'FOURTH YEAR');

        $rows = collect();

        if ($level === 'college') {
            $grouped = (clone $base)
                ->select('sc.College_id', 'sc.Student_id')
                ->get()
                ->groupBy('College_id');

            $colleges = College::where('Campus_id', $campusId)
                ->orderBy('College_name')
                ->get();

            $rows = $colleges->map(function ($college) use ($grouped) {
                $items      = $grouped->get($college->College_id, collect());
                $studentIds = $items->pluck('Student_id')->unique();

                return (object)[
                    'id'    => $college->College_id,
                    'name'  => $college->College_name,
                    'total' => $studentIds->count(),
                ];
            })->values();
        }

        elseif ($level === 'program') {
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

                return (object)[
                    'id'    => $program->Program_id,
                    'name'  => $program->Program_name,
                    'total' => $items->pluck('Student_id')->unique()->count(),
                ];
            })->filter()->values();
        }

        else { // major level
            if (!$collegeId || !$programId) {
                return redirect()->route('registrar.graduationlist', ['level' => 'college']);
            }

            $grouped = (clone $base)
                ->where('sc.College_id', $collegeId)
                ->where('sc.Program_id', $programId)
                ->select('sc.Major_id', 'sc.Student_id')
                ->get()
                ->groupBy('Major_id');

            $majors = Major::where('Campus_id', $campusId)
                ->where('College_id', $collegeId)
                ->where('Program_id', $programId)
                ->orderBy('Major_name')
                ->get()
                ->keyBy('Major_id');

            $rows = $grouped->map(function ($items, $mid) use ($majors) {
                $midInt = $mid === '' ? null : $mid;
                $major  = $midInt ? $majors->get($midInt) : null;

                return (object)[
                    'id'    => $midInt,
                    'name'  => $major ? $major->Major_name : 'No Major',
                    'total' => $items->pluck('Student_id')->unique()->count(),
                ];
            })->values();
        }

        return view('registrar.graduationlist', compact(
            'rows', 'userCampusId', 'userCampusName', 'level', 'campusId', 'collegeId', 'programId'
        ));
    }

    /**
     * Detailed list of graduating students (FOURTH YEAR)
     */
    public function students(Request $request)
    {
        $login = auth()->user();
        if (!$login) abort(403, 'Unauthorized');

        $designation = UserDesignation::with('campus')
            ->where('Login_id', $login->Login_id)
            ->first();

        if (!$designation) abort(403, 'No designation found for this user.');

        $campusId  = $designation->Campus_id;
        $collegeId = $request->query('college_id');
        $programId = $request->query('program_id');
        $majorId   = $request->query('major_id');

        if (!$collegeId || !$programId) {
            return redirect()->route('registrar.graduationlist', ['level' => 'college']);
        }

        $college = College::find($collegeId);
        $program = Program::find($programId);
        $major   = ($majorId && (int)$majorId !== 0) ? Major::find($majorId) : null;

        $query = StudentCourse::from('student_course as sc')
            ->join('student_manage as sm', 'sm.Student_id', '=', 'sc.Student_id')
            ->join('graduation_form as gf', 'gf.Student_id', '=', 'sc.Student_id')
            ->leftJoin('graduation_requirements as gr', 'gr.GraduationForm_id', '=', 'gf.GraduationForm_id')
            ->where('sc.Campus_id', $campusId)
            ->where('sc.College_id', $collegeId)
            ->where('sc.Program_id', $programId)
            ->where('sm.Year', 'FOURTH YEAR');

        if (!$majorId || $majorId == '0') {
            $query->whereNull('sc.Major_id');
        } else {
            $query->where('sc.Major_id', $majorId);
        }

        $students = $query->select(
            'sm.*', 'sc.Student_id', 'sc.Major_id',
            'gf.GraduationForm_id',
            'gr.GraduationReq_id',
            'gr.Approval_Sheet', 'gr.Certificate_Library',
            'gr.Barangay_Clearance', 'gr.Birth_Certificate',
            'gr.applicationform_grad', 'gr.reportofgrade_path',
            'gr.remarks', 'gr.status'
        )
        ->orderBy('sm.Last_name')
        ->get();

        return view('registrar.graduationlist_students', compact(
            'students', 'college', 'program', 'major'
        ));
    }


    /**
     * Stamp Registrar Name on Application Form PDF + Mark as Evaluated
     */
    public function evaluate(Request $request)
    {
        $request->validate([
            'graduation_req_id' => 'required|integer|exists:graduation_requirements,GraduationReq_id',
        ]);

        $login = auth()->user();
        if (!$login) abort(403, 'Unauthorized');

        // Get registrar name
        $designation = UserDesignation::with('user')
            ->where('Login_id', $login->Login_id)
            ->first();

        $registrarName = $designation && $designation->user
            ? $designation->user->full_name
            : 'Registrar Staff';

        // Get requirement row
        $req = GraduationRequirement::findOrFail($request->graduation_req_id);

        $relativePath = $req->applicationform_grad;
        if (!$relativePath) {
            return response()->json([
                'success' => false,
                'message' => 'No application form PDF found for this student.',
            ], 422);
        }

        // Normalize path
        $clean = ltrim($relativePath, '/');
        if (str_starts_with($clean, 'storage/')) {
            $clean = substr($clean, 8);
        }

        $pdfPath = storage_path('app/public/' . $clean);

        if (!file_exists($pdfPath)) {
            return response()->json([
                'success' => false,
                'message' => 'PDF file does not exist on the server.',
            ], 404);
        }

        // === Stamp Registrar Name ===
        try {
            $pdf = new FPDI();
            $pageCount = $pdf->setSourceFile($pdfPath);

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $tpl = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($tpl);

                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($tpl);

                if ($pageNo === 1) {
                    $pdf->SetFont('Helvetica', '', 12);
                    $pdf->SetTextColor(0, 0, 0);

                    // Final coordinates (adjust if needed)
                    $pdf->SetXY(150, 138);
                    $pdf->Cell(55, 5, utf8_decode($registrarName), 0, 0, 'C');
                }
            }

            $pdf->Output('F', $pdfPath);

            // Update STATUS
            $req->status = 'Evaluated';
            $req->save();

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update PDF: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Student evaluated successfully. PDF updated.',
        ]);
    }
}
