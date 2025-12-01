<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventStudentAssignment extends Model
{
    use HasFactory;

    protected $primaryKey = 'Assignment_id';

    protected $fillable = [
        'Event_id',
        'Student_id',
        'status',
    ];

    /**
     * Relation: Assignment belongs to an Event
     */
    public function event()
    {
        return $this->belongsTo(Event::class, 'Event_id', 'Event_id');
    }

    /**
     * Relation: Assignment belongs to a Student
     * Student model = StudentManage (based on student_manage table)
     */
    public function student()
    {
        return $this->belongsTo(StudentManage::class, 'Student_id', 'Student_id');
    }
}
