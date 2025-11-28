<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurriculumAy extends Model
{
    protected $table = 'curriculum_ay';
    protected $primaryKey = 'CurriculumAY_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'Campus_id',
        'College_id',
        'Program_id',
        'Major_id',
        
    ];

    public function program()
    {
        return $this->belongsTo(Program::class, 'Program_id', 'Program_id');
    }

    public function college()
    {
        return $this->belongsTo(\App\Models\College::class, 'College_id', 'College_id');
    }

    // ✅ Relationship to Campus (optional if needed)
    public function campus()
    {
        return $this->belongsTo(Campus::class, 'Campus_id', 'Campus_id');
    }

    public function major()
    {
        return $this->belongsTo(\App\Models\Major::class, 'Major_id', 'Major_id');
    }

    public function curriculums()
    { 
        return $this->hasMany(Curriculum::class, 'CurriculumAY_id', 'CurriculumAY_id'); 
    }
        

}
