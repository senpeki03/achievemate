<?php
// app/Models/StudentNotification.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class StudentNotification extends Model
{
    protected $table = 'student_notifications';
    protected $primaryKey = 'StudentNotification_id';
    protected $fillable = [
        'Student_id',
        'type',
        'title',
        'message',
        'data',
        'claim_token',
        'claimable',
        'claimed_at',
        'is_read',
    ];

    protected $casts = [
        'data'       => 'array',
        'claimable'  => 'boolean',
        'is_read'    => 'boolean',
        'claimed_at' => 'datetime',
    ];

    /* -------------------------------- Relations ------------------------------- */

    public function student()
    {
        // Adjust model/classname if your student model differs
        return $this->belongsTo(StudentManage::class, 'Student_id', 'Student_id');
    }

    /* --------------------------------- Scopes -------------------------------- */

    public function scopeForStudent(Builder $q, int $studentId): Builder
    {
        return $q->where('Student_id', $studentId);
    }

    public function scopeUnread(Builder $q): Builder
    {
        return $q->where('is_read', false);
    }

    public function scopeUnreadForStudent(Builder $q, int $studentId): Builder
    {
        return $q->forStudent($studentId)->unread();
    }

    public function scopeClaimable(Builder $q): Builder
    {
        return $q->where('claimable', true)->whereNull('claimed_at');
    }

    /* ------------------------------ Convenience ------------------------------ */

    /**
     * Ensure there is a claim token (idempotent).
     */
    public function ensureClaimToken(): self
    {
        if (!$this->claim_token) {
            $this->claim_token = Str::random(40);
            $this->save();
        }
        return $this;
    }

    /**
     * Mark as read (idempotent).
     */
    public function markRead(): self
    {
        if (!$this->is_read) {
            $this->is_read = true;
            $this->save();
        }
        return $this;
    }

    /**
     * Claim the notification (e.g., to move badge/certificate to portfolio).
     * Returns false if already claimed or not claimable.
     */
    public function claim(): bool
    {
        if (!$this->claimable || $this->claimed_at) {
            return false;
        }
        $this->claimed_at = Carbon::now();
        $this->save();
        return true;
    }
}
