<?php

namespace App\Observers;

use App\Models\StudentGrade;
use App\Services\StudentProgressService;

class StudentGradeObserver
{
    public function created(StudentGrade $grade): void
    {
        // every insert ng bagong grade, i-sync progress
        StudentProgressService::syncFromGrades((int) $grade->Student_id);
    }

    public function updated(StudentGrade $grade): void
    {
        StudentProgressService::syncFromGrades((int) $grade->Student_id);
    }
}
