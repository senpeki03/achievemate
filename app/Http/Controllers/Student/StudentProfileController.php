<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\StudentManage;

class StudentProfileController extends Controller
{
    public function index()
    {
        // normalize session key
        $studentId = session('student_id') ?? session('Student_id');
        abort_if(!$studentId, 403, 'No active student session.');

        // Eager-load: curriculum -> curriculumAy -> (college, program)
        $student = StudentManage::with([
            'curriculum.curriculumAy.college',
            'curriculum.curriculumAy.program',
        ])->findOrFail($studentId);

        // Read values from your real columns
        $fullName = trim(
            ($student->First_name ?? '') . ' ' .
            ($student->Middle_name ?? '') . ' ' .
            ($student->Last_name ?? '')
        ) ?: 'Student';

        $college = $student->curriculum?->curriculumAy?->college?->College_name ?? '—';
        $program = $student->curriculum?->curriculumAy?->program?->Program_name ?? '—';
        $ayLabel = $student->curriculum?->curriculumAy?->Academic_year ?? '—';

        return view('student.profile', compact('student', 'fullName', 'college', 'program', 'ayLabel'));
    }
}
