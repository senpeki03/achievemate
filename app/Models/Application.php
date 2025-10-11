<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Application extends Model
{
    protected $table = 'application';
    protected $primaryKey = 'Application_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'Student_id','Type','File_name','File_data','GWA','Rank','Status'
    ];

    // Never leak the blob in any JSON
    protected $hidden = ['File_data'];

    protected $casts = [
        'GWA' => 'float',
    ];

    public function student()
    {
        return $this->belongsTo(\App\Models\StudentManage::class, 'Student_id', 'Student_id');
    }

    /* ---------- Scopes (optional but nice) ---------- */
    public function scopeWithStatusIn(Builder $q, array $statuses): Builder
    {
        // Case-insensitive status filter (works even if DB stores mixed case)
        return $q->where(function ($qq) use ($statuses) {
            foreach ($statuses as $s) {
                $qq->orWhereRaw('UPPER(Status) = ?', [mb_strtoupper($s)]);
            }
        });
    }

    public function scopeOrderNewest(Builder $q): Builder
    {
        // Your table has no created_at/updated_at, so sort by PK
        return $q->orderByDesc('Application_id');
    }
}
