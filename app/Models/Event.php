<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $primaryKey = 'Event_id';

    protected $fillable = [
        'UserDesignation_id',
        'EventType_id',
        'title',
        'description',
        'start_at',
        'end_at',
        'number_of_students',
        'assigned_count',
        'status',
    ];

    /**
     * Relation: Event belongs to a UserDesignation
     */
    public function userDesignation()
    {
        return $this->belongsTo(UserDesignation::class, 'UserDesignation_id', 'UserDesignation_id');
    }

    /**
     * Relation: Event has many invited students
     * (If you will make event_student_assignments table)
     */
    public function assignments()
    {
        return $this->hasMany(EventStudentAssignment::class, 'Event_id', 'Event_id');
        // adjust class/keys if your names differ
    }

    public function invites()
    {
        // event_student_assignments table name, adjust if different
        return $this->hasMany(EventStudentAssignment::class, 'Event_id', 'Event_id');
    }

    public function eventType()
  {
    return $this->belongsTo(\App\Models\EventType::class, 'EventType_id', 'EventType_id');
  }
}
