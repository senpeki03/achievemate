<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Curriculum;

class Curriculumsubject extends Model
{
    protected $table = 'curriculum_subjects';
    protected $primaryKey = 'subject_id';
    public $timestamps = false;

    protected $fillable = [
        'curriculum_id',
        'Code',
        'Course_Title',
        'units',
        'lec',
        'lab',
        'prerequisite',
        'total_units',
        'total_lec',
        'total_lab',
        'year_level',
        'semester',
        'track'
    ];

    public function curriculum()
    {
        return $this->belongsTo(Curriculum::class, 'curriculum_id', 'curriculum_id');
    }
}
