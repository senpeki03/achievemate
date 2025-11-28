<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Major extends Model
{
    protected $table = 'major';
    protected $primaryKey = 'Major_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'Campus_id',
        'College_id',
        'Program_id',
        'Abbreviation',
        'Major_name',
        'Created_at'
    ];

    // 🔹 balik relation: each major belongs to a program
    public function program()
    {
        return $this->belongsTo(Program::class, 'Program_id', 'Program_id');
    }

    public function curriculumAy()
    {
        return $this->hasMany(CurriculumAY::class, 'Major_id', 'Major_id');
    }
}
