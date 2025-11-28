<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Grades extends Model
{
    protected $table = 'grades';
    protected $primaryKey = 'Grades_id';
    public $timestamps = false;
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'Student_id',
        'image',
        'sem',
        'academic_year',
    ];

    protected $casts = [
        'Grades_id'  => 'integer',
        'Student_id' => 'integer',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentManage::class, 'Student_id', 'Student_id');
    }

    /** Filter by Student_id */
    public function scopeForStudent($query, int $studentId)
    {
        return $query->where('Student_id', $studentId);
    }

    /** Filter by Student + sem + academic_year */
    public function scopeForTerm($query, int $studentId, string $sem, string $ayLabel)
    {
        return $query->where('Student_id', $studentId)
            ->where('sem', $sem)
            ->where('academic_year', $ayLabel);
    }

    /** Accessor para sa base64 image */
    public function getImageBase64Attribute(): ?string
    {
        if (empty($this->image)) {
            return null;
        }
        $base64 = base64_encode($this->image);
        // png ang gino-generate ng Imagick
        return "data:image/png;base64,{$base64}";
    }
}
