<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    protected $table = 'program';
    protected $primaryKey = 'Program_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'Campus_id',
        'College_id',
        'Abbreviation',
        'Program_name',
        'Created_at'
    ];
    

    // If Program hasOne Major (most likely based on your use case)
    public function major()
    {
        return $this->hasOne(Major::class, 'Program_id');
    }

    // OR: if Program hasMany Majors (if there are multiple majors per program)
    public function majors()
    {
        return $this->hasMany(Major::class, 'Program_id');
    }
}