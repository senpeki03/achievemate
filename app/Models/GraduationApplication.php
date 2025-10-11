<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class GraduationApplication extends Model
{
    protected $table      = 'graduation_applications';
    protected $primaryKey = 'Graduation_id';
    public $incrementing  = true;
    protected $keyType    = 'int';

    // IMPORTANT: keep the exact column casing here
    protected $fillable = [
        'Student_id','College_id','Program_id','application_no','term_end',
        'status','submitted_at','initial_reviewed_at','returned_for_compliance_at',
        'resubmitted_at','final_reviewed_at','deliberated_at','decided_at',
        'endorsed_at','notified_at','initial_reviewer_id','final_reviewer_id',
        'decider_id','endorser_id','remarks','deficiency_notes',
        // optional upload/OCR fields used by your mobile app
        'cor_image_path','grades_image_path','cor_ocr_text','grades_ocr_text',
    ];

    protected $casts = [
        'deficiency_notes' => 'array',
        'submitted_at' => 'datetime',
        'initial_reviewed_at' => 'datetime',
        'returned_for_compliance_at' => 'datetime',
        'resubmitted_at' => 'datetime',
        'final_reviewed_at' => 'datetime',
        'deliberated_at' => 'datetime',
        'decided_at' => 'datetime',
        'endorsed_at' => 'datetime',
        'notified_at' => 'datetime',
        'term_end' => 'date',
    ];

    // RELATIONS
    public function student()
    {
        // column is Student_id (capital S)
        return $this->belongsTo(User::class, 'Student_id');
    }

    public function requirements()
    {
        return $this->hasMany(
            GraduationApplicationRequirement::class,
            'Graduation_id',   // child's FK column
            'Graduation_id'    // parent's PK
        );
    }

    public function logs()
    {
        return $this->hasMany(GraduationStatusLog::class, 'application_id')->latest();
    }

    // scopes
    public function scopeStatus($q, $status)
    {
        return $q->where('status', $status);
    }

    // computed
    protected function isActionableByStudent(): Attribute
    {
        return Attribute::get(fn () => in_array($this->status, ['draft','for_compliance']));
    }
}
