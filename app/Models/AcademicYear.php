<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicYear extends Model
{
    use HasFactory;

    /** 
     * Table name (optional if naming follows convention)
     */
    protected $table = 'academic_years';
    protected $primaryKey = 'academic_year_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'label',
        'start_date',
        'end_date',
        'is_current',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'is_current' => 'boolean',
    ];

    public function getDisplayLabelAttribute(): string
    {
        return 'AY ' . $this->label;
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', 1);
    }

    public function scopeByLabel($query, string $label)
    {
        return $query->where('label', $label);
    }
}
