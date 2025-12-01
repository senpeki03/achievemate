<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Models\Evaluation; 

class StudentManage extends Model
{
    protected $table = 'student_manage';
    protected $primaryKey = 'Student_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'Login_id',
        'curriculum_id',
        'SRCODE',
        'First_name',
        'Middle_name',
        'Last_name',
        'Contact',
        'Year',
        'Email',
        'Academic_year', // <- student’s own AY lives here now
    ];

    /* -------------------------------------------
     | Base relationships (native & reliable)
     |--------------------------------------------
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(function () {
            $mid = $this->Middle_name
                ? ' ' . strtoupper(substr($this->Middle_name, 0, 1)) . '.'
                : '';

            return strtoupper("{$this->Last_name}, {$this->First_name}{$mid}");
        });
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluation::class, 'Student_id', 'Student_id');
    }

    // optional helper to grab the *latest* evaluation
    protected function latestEvaluation(): Attribute
    {
        return Attribute::get(function () {
            return $this->evaluations()
                        ->orderByDesc('Date')
                        ->first();
        });
    }

    public function applications()
    {
        return $this->hasMany(Application::class, 'Student_id', 'Student_id');
    }

    public function studentCourse()
    {
        return $this->hasMany(StudentCourse::class, 'Student_id', 'Student_id');
    }

    // StudentManage -> Curriculum
    public function curriculum()
    {
        // FK on student_manage: curriculum_id -> curriculum.curriculum_id
        return $this->belongsTo(Curriculum::class, 'curriculum_id', 'curriculum_id');
    }

    // StudentManage -> Login (if you need it)
    public function login()
    {
        return $this->belongsTo(Login::class, 'Login_id', 'Login_id');
    }

    // Convenience: StudentManage -> CurriculumAy via Curriculum
    // (no direct Eloquent relation possible without extra package; use accessor below)
    // public function curriculumAy() ... not a native relation

    /* -------------------------------------------
     | Computed accessors (lazy, safe, no packages)
     |--------------------------------------------
     | These traverse existing relations at runtime.
     | Use: $student->curriculumAy, $student->college, etc.
     */

    public function graduationForm()
    {
        return $this->hasOne(GraduationForm::class, 'Student_id', 'Student_id');
    }

    protected function curriculumAy(): Attribute
    {
        return Attribute::get(function () {
            // curriculum()->first() is cached by Eloquent when eager loaded
            $curr = $this->curriculum;
            return $curr?->curriculumAy; // relies on Curriculum::curriculumAy()
        });
    }

    protected function college(): Attribute
    {
        return Attribute::get(function () {
            return $this->curriculumAy?->college ?? null;
        });
    }

    protected function program(): Attribute
    {
        return Attribute::get(function () {
            return $this->curriculumAy?->program ?? null;
        });
    }

    protected function major(): Attribute
    {
        return Attribute::get(function () {
            return $this->curriculumAy?->major ?? null;
        });
    }

    /* -------------------------------------------
     | Helpful scopes for listing with joins
     |--------------------------------------------
     | Use these if you need to query students with
     | college/program/major names in one shot.
     */

    public function scopeWithCurriculumJoins($query)
    {
        return $query
            ->leftJoin('curriculum as c', 'c.curriculum_id', '=', 'student_manage.curriculum_id')
            ->leftJoin('curriculum_ay as ay', 'ay.CurriculumAY_id', '=', 'c.CurriculumAY_id')
            ->leftJoin('college as col', 'col.College_id', '=', 'ay.College_id')
            ->leftJoin('program as prog', 'prog.Program_id', '=', 'ay.Program_id')
            ->leftJoin('major as maj', 'maj.Major_id', '=', 'ay.Major_id')
            ->addSelect([
                'student_manage.*',
                'c.Curriculum_name as _curriculum_name',
                // Student-owned AY:
                'student_manage.Academic_year as _student_academic_year',
                'col.College_name as _college_name',
                'prog.Program_name as _program_name',
                'maj.Major_name as _major_name',
            ]);
    }

    public function eventAssignments()
    {
        return $this->hasMany(EventStudentAssignment::class, 'Student_id', 'Student_id');
    }
}
