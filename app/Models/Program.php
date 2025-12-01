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

    // 🔹 one program -> many majors (usually ganito)
    public function majors()
    {
        // foreign key sa `major` table, local key sa `program`
        return $this->hasMany(Major::class, 'Program_id', 'Program_id');
    }

    // optional kung gusto mo lang kumuha ng "primary" major
    public function major()
    {
        return $this->hasOne(Major::class, 'Program_id', 'Program_id');
    }

    public function college()
    {
        return $this->belongsTo(College::class, 'College_id', 'College_id');
    }
}
