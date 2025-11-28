<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentGrade extends Model
{
    use HasFactory;

    /** -----------------------------------------------------------------
     *  Table & Keys
     *  -----------------------------------------------------------------*/
    protected $table = 'student_grades';
    protected $primaryKey = 'student_grade_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    /** -----------------------------------------------------------------
     *  Mass Assignment
     *  -----------------------------------------------------------------*/
    protected $fillable = [
        'Student_id',
        'course_code',
        'subject_id',
        'academic_year_id',
        'semester',
        'grade',
        'section',
        'instructor',
        'remarks',
    ];

    /** -----------------------------------------------------------------
     *  Attribute Casting
     *  -----------------------------------------------------------------*/
    protected $casts = [
        'student_grade_id' => 'integer',
        'Student_id'       => 'integer',
        'subject_id'       => 'integer',
        'academic_year_id' => 'integer',
    ];

    /** -----------------------------------------------------------------
     *  Relationships
     *  -----------------------------------------------------------------*/

    /**
     * Student this grade belongs to.
     * Using direct DB query approach since StudentManage model might not exist
     */
    public function student()
    {
        // We'll handle this differently in the controller
        return null;
    }

    /**
     * Curriculum subject relationship.
     * Note: Using Curriculumsubject (lowercase 's') based on your model
     */
    public function curriculumSubject(): BelongsTo
    {
        return $this->belongsTo(Curriculumsubject::class, 'subject_id', 'subject_id');
    }

    /**
     * Academic year of this record.
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id', 'academic_year_id');
    }

    /** -----------------------------------------------------------------
     *  Scopes
     *  -----------------------------------------------------------------*/

    public function scopeByStudent($query, int $studentId)
    {
        return $query->where('Student_id', $studentId);
    }

    public function scopeBySubject($query, int $subjectId)
    {
        return $query->where('subject_id', $subjectId);
    }

    public function scopeByAcademicYear($query, int $ayId)
    {
        return $query->where('academic_year_id', $ayId);
    }

    public function scopeBySemester($query, string $semester)
    {
        return $query->where('semester', $semester);
    }

    public function scopeBySection($query, string $section)
    {
        return $query->where('section', $section);
    }

    public function scopeWithCourse($query, string $courseCode)
    {
        return $query->where('course_code', $courseCode);
    }

    /** -----------------------------------------------------------------
     *  Accessors for easy data retrieval
     *  -----------------------------------------------------------------*/

    /**
     * Get course title directly
     */
    public function getCourseTitleAttribute()
    {
        if ($this->curriculumSubject) {
            return $this->curriculumSubject->Course_Title; // Note: Capital 'C' and 'T'
        }
        
        // Fallback: direct database query
        $subject = \DB::table('curriculum_subjects')
                     ->where('subject_id', $this->subject_id)
                     ->first();
        
        return $subject ? $subject->Course_Title : 'Course Title Not Available';
    }
    

    /**
     * Get course units directly
     */
    public function getCourseUnitsAttribute()
    {
        if ($this->curriculumSubject) {
            return $this->curriculumSubject->units;
        }
        
        // Fallback: direct database query
        $subject = \DB::table('curriculum_subjects')
                     ->where('subject_id', $this->subject_id)
                     ->first();
        
        return $subject ? $subject->units : 'N/A';
    }

    /** -----------------------------------------------------------------
     *  Mutators (light hygiene)
     *  -----------------------------------------------------------------*/

    public function setCourseCodeAttribute($value): void
    {
        $this->attributes['course_code'] = trim((string) $value);
    }

    public function setSectionAttribute($value): void
    {
        $this->attributes['section'] = trim((string) $value);
    }

    public function setInstructorAttribute($value): void
    {
        $this->attributes['instructor'] = trim((string) $value);
    }
}