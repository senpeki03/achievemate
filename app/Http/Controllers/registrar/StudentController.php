<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Campus;
use App\Models\College;
use App\Models\Program;
use App\Models\Curriculum;
use App\Models\CurriculumAy;   // ✅ use correct class name
use App\Models\Major;
use App\Models\StudentManage;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $campuses = Campus::all();
        $colleges = College::all();
        $programs = Program::all();
        $majors   = Major::all();

        // ✅ Select only JSON-safe fields (exclude the LONGBLOB File_data)
        $curriculums = Curriculum::join('curriculum_ay as ay', 'curriculum.CurriculumAY_id', '=', 'ay.CurriculumAY_id')
        ->select([
            'curriculum.curriculum_id',
            'curriculum.CurriculumAY_id',
            'curriculum.Curriculum_name',
            'ay.Campus_id','ay.College_id','ay.Program_id','ay.Major_id','ay.Academic_year',
        ])->get();


        $collegeName = null;
        $programName = null;

        if ($request->has('curriculum_id')) {
            $curriculum_id = $request->curriculum_id;
            $students = StudentManage::where('curriculum_id', $curriculum_id)->get();

            $curr = Curriculum::find($curriculum_id);
            if ($curr) {
                $currAy = CurriculumAy::find($curr->CurriculumAY_id);
                if ($currAy) {
                    $college = College::find($currAy->College_id);
                    $program = Program::find($currAy->Program_id);
                    $collegeName = $college?->College_name;
                    $programName = $program?->Program_name;
                }
            }
        } else {
            $students = StudentManage::all();
        }

        return view('registrar.student', compact(
            'campuses','colleges','programs','majors',
            'curriculums','students','collegeName','programName'
        ));
    }

    public function studentList(Request $request)
    {
        $students = collect();
        $collegeName = null;
        $programName = null;

        if ($request->has('curriculum_id')) {
            $curriculum_id = $request->curriculum_id;
            $students = StudentManage::where('curriculum_id', $curriculum_id)->get();

            $curr = Curriculum::find($curriculum_id);
            if ($curr) {
                $currAy = CurriculumAy::find($curr->CurriculumAY_id);
                if ($currAy) {
                    $college = College::find($currAy->College_id);
                    $program = Program::find($currAy->Program_id);
                    $collegeName = $college?->College_name;
                    $programName = $program?->Program_name;
                }
            }
        }

        return view('registrar.studentlist', compact('students','collegeName','programName'));
    }

    public function deleteStudent($id)
    {
        try {
            StudentManage::where('Student_id', $id)->delete();
            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
