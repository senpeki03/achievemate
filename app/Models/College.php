<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class College extends Model
{
    protected $table = 'college';
    protected $primaryKey = 'College_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'Campus_id',
        'Abbreviation',
        'College_name',
        'Created_at'
    ];
    

    public function campus()
    {
        return $this->belongsTo(Campus::class, 'Campus_id', 'Campus_id');
    }

    public function students()
    {
        // Assuming students are related through 'curriculum_id', change this as per your correct relationship
        return $this->hasManyThrough(StudentManage::class, CurriculumAy::class, 'College_id', 'CurriculumAY_id', 'College_id', 'CurriculumAY_id');
    }

        public function curriculumAy()
    {
        return $this->hasMany(CurriculumAy::class, 'College_id', 'College_id');
    }

    // Relationship with Curriculum (One College has many Curriculums through CurriculumAy)
    public function curriculums()
    {
        return $this->hasManyThrough(Curriculum::class, CurriculumAy::class, 'College_id', 'CurriculumAY_id', 'College_id', 'CurriculumAY_id');
    }

        public function userDesignations()
    {
        return $this->hasMany(UserDesignation::class, 'College_id', 'College_id');
    }
}
