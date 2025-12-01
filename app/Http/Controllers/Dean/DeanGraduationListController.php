<?php

namespace App\Http\Controllers\Dean;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserDesignation;
use App\Models\StudentCourse;
use App\Models\StudentManage;
use App\Models\GraduationForm;
use App\Models\GraduationRequirement;
use App\Models\College;
use App\Models\Program;
use App\Models\Major;

class DeanGraduationListController extends Controller
{
    /**
     * HIERARCHY PAGE FOR DEAN SIDE
     * Level = program | major
     */
    public function index(Request $request)
    {
        $login = auth()->user();
        if (!$login) abort(403, 'Unauthorized');

        // ===== GET DEAN’S COLLEGE =====
        $designation = UserDesignation::with(['college'])
            ->where('Login_id', $login->Login_id)
            ->first();

        if (!$designation || !$designation->College_id) {
            abort(403, 'You are not assigned to a College.');
        }

        $deanCollege = $designation->college;
        $collegeId   = $deanCollege->College_id;

        // ===== LEVEL: program | major =====
        $level     = $request->query('level', 'program');
        $programId = $request->query('program_id');

        // ===== BASE QUERY WITH EVALUATED FILTER =====
        $base = StudentCourse::from('student_course as sc')
            ->join('student_manage as sm', 'sm.Student_id', '=', 'sc.Student_id')
            ->join('graduation_form as gf', 'gf.Student_id', '=', 'sc.Student_id')
            ->join('graduation_requirements as gr', 'gr.GraduationForm_id', '=', 'gf.GraduationForm_id')
            ->where('sc.College_id', $collegeId)
            ->where('sm.Year', 'FOURTH YEAR')
            ->where('gr.status', 'Evaluated');   // ⭐ ONLY EVALUATED

        // ======================================================
        // ================ PROGRAM LEVEL ========================
        // ======================================================
        if ($level === 'program') {

            $grouped = (clone $base)
                ->select('sc.Program_id', 'sc.Student_id')
                ->get()
                ->groupBy('Program_id');

            $programs = Program::where('College_id', $collegeId)
                ->orderBy('Program_name')
                ->get()
                ->keyBy('Program_id');

            $rows = $grouped->map(function ($items, $pid) use ($programs) {
                $program = $programs->get($pid);
                if (!$program) return null;

                $studentIds = $items->pluck('Student_id')->unique();
                return (object)[
                    'id'    => $program->Program_id,
                    'name'  => $program->Program_name,
                    'total' => $studentIds->count(),
                ];
            })->filter()->values();

            return view('dean.reviewedgraduationlist', [
                'rows'         => $rows,
                'title'        => 'Reviewed Graduation List',
                'level'        => 'program',
                'collegeName'  => $deanCollege->College_name,
                'collegeId'    => $collegeId,
                'programId'    => null,
            ]);
        }

        // ======================================================
        // ================ MAJOR LEVEL =========================
        // ======================================================
        if ($level === 'major') {

            if (!$programId) {
                return redirect()->route('dean.reviewedgraduationlist', ['level' => 'program']);
            }

            $grouped = (clone $base)
                ->where('sc.Program_id', $programId)
                ->select('sc.Major_id', 'sc.Student_id')
                ->get()
                ->groupBy('Major_id');

            $majors = Major::where('College_id', $collegeId)
                ->where('Program_id', $programId)
                ->orderBy('Major_name')
                ->get()
                ->keyBy('Major_id');

            $rows = $grouped->map(function ($items, $mid) use ($majors) {
                $midInt = $mid === '' ? null : $mid;
                $major  = $midInt ? $majors->get($midInt) : null;

                $name = $major ? $major->Major_name : 'No Major';

                $studentIds = $items->pluck('Student_id')->unique();

                return (object)[
                    'id'    => $midInt,
                    'name'  => $name,
                    'total' => $studentIds->count(),
                ];
            })->values();

            return view('dean.reviewedgraduationlist', [
                'rows'         => $rows,
                'title'        => 'Reviewed Graduation List',
                'level'        => 'major',
                'collegeName'  => $deanCollege->College_name,
                'collegeId'    => $collegeId,
                'programId'    => $programId,
            ]);
        }

        abort(404, 'Invalid level');
    }

    /**
     * ======================
     * STUDENT LIST (DETAILED)
     * ======================
     */
    public function students(Request $request)
    {
        $login = auth()->user();
        if (!$login) abort(403, 'Unauthorized');

        $designation = UserDesignation::with('college')
            ->where('Login_id', $login->Login_id)
            ->first();

        if (!$designation || !$designation->College_id) {
            abort(403, 'No College assigned to this Dean.');
        }

        $collegeId = $designation->College_id;

        $programId = $request->query('program_id');
        $majorId   = $request->query('major_id');

        if (!$programId) {
            return redirect()->route('dean.reviewedgraduationlist', ['level' => 'program']);
        }

        $college = College::find($collegeId);
        $program = Program::find($programId);
        $major   = ($majorId && (int)$majorId !== 0) ? Major::find($majorId) : null;

        // ===== QUERY ONLY EVALUATED STUDENTS =====
        $query = StudentCourse::from('student_course as sc')
            ->join('student_manage as sm', 'sm.Student_id', '=', 'sc.Student_id')
            ->join('graduation_form as gf', 'gf.Student_id', '=', 'sc.Student_id')
            ->join('graduation_requirements as gr', 'gr.GraduationForm_id', '=', 'gf.GraduationForm_id')
            ->where('sc.College_id', $collegeId)
            ->where('sc.Program_id', $programId)
            ->where('sm.Year', 'FOURTH YEAR')
            ->where('gr.status', 'Evaluated'); // ⭐ ONLY EVALUATED

        if ($majorId === '0' || $majorId === 0 || $majorId === null || $majorId === '') {
            $query->whereNull('sc.Major_id');
        } else {
            $query->where('sc.Major_id', $majorId);
        }

        $students = $query->select(
                'sm.*',
                'sc.Student_id',
                'sc.Major_id',
                'gf.GraduationForm_id',
                'gr.GraduationReq_id',
                'gr.Approval_Sheet',
                'gr.Certificate_Library',
                'gr.Barangay_Clearance',
                'gr.Birth_Certificate',
                'gr.applicationform_grad',
                'gr.reportofgrade_path',
                'gr.remarks',
                'gr.status'
            )
            ->orderBy('sm.Last_name')
            ->get();

        return view('dean.reviewedstudentlist', [
            'students' => $students,
            'college'  => $college,
            'program'  => $program,
            'major'    => $major,
        ]);
    }
}
