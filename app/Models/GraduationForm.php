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
        'Birthdate',
        'PlaceofBirth',
        'HomeAddress',
        'ZIP_Code',
        'Sec_Grad',
        'Sec_Grad_Year',
        'Elem_Grad',
        'Elem_Grad_Year',
    ];

    protected $casts = [
        'Birthdate' => 'date',
        'ZIP_Code'  => 'integer',
    ];

    public function student()
    {
        return $this->belongsTo(StudentManage::class, 'Student_id', 'Student_id');
    }
}
