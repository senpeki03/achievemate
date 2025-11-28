<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GraduationRequirement extends Model
{
    protected $table = 'graduation_requirements';

    protected $primaryKey  = 'GraduationReq_id';
    public $incrementing   = true;
    protected $keyType     = 'int';
    public $timestamps     = false;

    protected $fillable = [
        'GraduationForm_id',
        'Approval_Sheet',
        'Certificate_Library',
        'Barangay_Clearance',
        'Birth_Certificate',
        'applicationform_grad',
        'reportofgrade_path',
        'remarks',
    ];

    public function form()
    {
        return $this->belongsTo(GraduationForm::class, 'GraduationForm_id', 'GraduationForm_id');
    }
}
