<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Latin extends Model
{
    protected $table = 'latin';
    protected $primaryKey = 'Latin_id';
    public $timestamps = false;

    protected $fillable = [
        'Student_id',
        'GraduationForm_id',   // <-- correct column name
        'Consent',
    ];

    // Optional relationships
    public function graduationForm()
    {
        return $this->belongsTo(\App\Models\GraduationForm::class, 'GraduationForm_id', 'GraduationForm_id');
    }
}
