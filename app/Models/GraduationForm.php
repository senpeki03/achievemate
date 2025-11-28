<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GraduationForm extends Model
{
    protected $table = 'graduation_form';
    protected $primaryKey = 'GraduationForm_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'Student_id',
        'Birthdate',           // typo sa column pero ok lang basta match sa DB
        'PlaceofBirth',
        'HomeAddress',
        'ZIP_Code',
        'Sec_Grad',
        'Sec_Grad_Year',
        'Elem_Grad',
        'Elem_Grad_Year',
        'Scholarship_grant',
        'Guardian_1',
        'Guardian_1_Contact',
        'Guardian_2',
        'Guardian_2_Contact',
    ];

    protected $casts = [
        'Birthdate' => 'date',
        'ZIP_Code' => 'integer',
    ];

    public function student()
    {
        return $this->belongsTo(StudentManage::class, 'Student_id', 'Student_id');
    }

    // ✅ Add this: 1 graduation_form → 1 graduation_requirements row
    public function requirement()
    {
        return $this->hasOne(GraduationRequirement::class, 'GraduationForm_id', 'GraduationForm_id');
    }

    // If ever you allow multiple requirement rows per form, use hasMany instead:
    // public function requirements()
    // {
    //     return $this->hasMany(GraduationRequirement::class, 'GraduationForm_id', 'GraduationForm_id');
    // }
}
