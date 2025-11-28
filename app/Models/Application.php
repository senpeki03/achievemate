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
        'Student_id',
        'Post_id',
        'Type',
        'File_name',
        'File_data',
        'GWA',
        'Rank',
        'Status',
    ];

    protected $hidden = ['File_data'];

    protected $casts = [
        'GWA' => 'float',
    ];

    public function student()
    {
        return $this->belongsTo(\App\Models\StudentManage::class, 'Student_id', 'Student_id');
    }

    public function post()
    {
        return $this->belongsTo(Post::class, 'Post_id', 'Post_id');
    }

    // 🔔 All recipients (including Program Chair)
    public function recipients()
    {
        return $this->hasMany(ApplicationRecipient::class, 'Application_id', 'Application_id');
    }

    public function scopeWithStatusIn(Builder $q, array $statuses): Builder
    {
        return $q->where(function ($qq) use ($statuses) {
            foreach ($statuses as $s) {
                $qq->orWhereRaw('UPPER(Status) = ?', [mb_strtoupper($s)]);
            }
        });
    }

    public function scopeOrderNewest(Builder $q): Builder
    {
        return $q->orderByDesc('Application_id');
    }
}
