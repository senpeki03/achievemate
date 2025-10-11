<?php
// app/Models/Portfolio.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Portfolio extends Model
{
    protected $table = 'portfolios';
    protected $primaryKey = 'Portfolio_id';
    protected $fillable = [
        'Student_id',
        'type',              // e.g., 'DeanLister'
        'title',
        'description',
        'badge_path',
        'certificate_path',
    ];

    protected $casts = [
        // no special casts needed, but you can add later if you store arrays/JSON
    ];

    /* -------------------------------- Relations ------------------------------- */

    public function student()
    {
        return $this->belongsTo(StudentManage::class, 'Student_id', 'Student_id');
    }

    /* --------------------------------- Scopes -------------------------------- */

    public function scopeForStudent(Builder $q, int $studentId): Builder
    {
        return $q->where('Student_id', $studentId);
    }

    public function scopeOfType(Builder $q, string $type): Builder
    {
        return $q->where('type', $type);
    }

    /* ------------------------------ Convenience ------------------------------ */

    /**
     * Quick helper to create a portfolio entry from an award payload.
     * Example payload keys:
     *  - title, description, badge_path, certificate_path
     */
    public static function addAward(
        int $studentId,
        string $type,
        array $attrs = []
    ): self {
        return static::create([
            'Student_id'       => $studentId,
            'type'             => $type,
            'title'            => $attrs['title']            ?? 'Award',
            'description'      => $attrs['description']      ?? null,
            'badge_path'       => $attrs['badge_path']       ?? null,
            'certificate_path' => $attrs['certificate_path'] ?? null,
        ]);
    }
}
