<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Curriculum extends Model
{
    protected $table = 'curriculum';
    protected $primaryKey = 'curriculum_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $hidden = ['File_data'];


    protected $fillable = [
        'CurriculumAY_id',
        'Curriculum_name',
        'File_data',
        'Academic_year'
    ];

    // Automatically extract readable filename
    public function getOriginalNameAttribute()
    {
        return \Illuminate\Support\Str::after($this->Curriculum_name, '_');
    }

        public function program()
    {
        return $this->belongsTo(Program::class, 'Program_id', 'Program_id');
    }

    public function college()
    {
        return $this->belongsTo(College::class, 'College_id', 'College_id');
    }

    public function curriculumAy()
    {
        return $this->belongsTo(\App\Models\CurriculumAy::class, 'CurriculumAY_id', 'CurriculumAY_id');
    }

}
