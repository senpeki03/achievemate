<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
    ];
    
    public function curriculum()
    {
        return $this->belongsTo(\App\Models\Curriculum::class, 'curriculum_id', 'curriculum_id');
    }


    public function login()
    {
        return $this->belongsTo(Login::class, 'Login_id', 'Login_id');
    }

    // Access College through Curriculum and CurriculumAy
    public function college()
    {
        return $this->hasOneThrough(
            College::class, 
            CurriculumAy::class, 
            'CurriculumAY_id', 
            'College_id', 
            'curriculum_id', 
            'College_id'
        );
    }

    // Access Program through Curriculum and CurriculumAy
    public function program()
    {
        return $this->hasOneThrough(
            Program::class, 
            CurriculumAy::class, 
            'CurriculumAY_id', 
            'Program_id', 
            'curriculum_id', 
            'Program_id'
        );
    }

    public function curriculumAy()
    {
        // Define the correct relationship to CurriculumAy using curriculum_id
        return $this->belongsToThrough(CurriculumAy::class, Curriculum::class, 'curriculum_id', 'CurriculumAY_id');
    }

    public function application()
    {
        return $this->hasOne(Application::class, 'Student_id', 'Student_id');
    }

    public function graduationForms()
    {
        return $this->hasMany(GraduationForm::class, 'Student_id', 'Student_id');
    }


}
