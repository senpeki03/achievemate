<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentCourse extends Model
{
  protected $table = 'student_course';
  protected $primaryKey = 'StudentCourse_id';
  public $incrementing = true;
  protected $keyType = 'int';
  public $timestamps = false;

  protected $fillable = [
    'Campus_id',
    'College_id',
    'Program_id',
    'Major_id',
    'Student_id',
    'curriculum_id'
  ];

  // ===== RELATIONSHIPS =====

  public function campus()
  {
    return $this->belongsTo(Campus::class, 'Campus_id', 'Campus_id');
  }

  public function college()
  {
    return $this->belongsTo(College::class, 'College_id', 'College_id');
  }

  public function program()
  {
    return $this->belongsTo(Program::class, 'Program_id', 'Program_id');
  }

  public function major()
  {
    return $this->belongsTo(Major::class, 'Major_id', 'Major_id');
  }

  public function student()
  {
    return $this->belongsTo(StudentManage::class, 'Student_id', 'Student_id');
  }

  public function curriculum()
  {
    return $this->belongsTo(Curriculum::class, 'curriculum_id', 'curriculum_id');
  }
}
