<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    protected $table = 'profile';
    protected $primaryKey = 'Profile_id';
    public $timestamps = false;

    protected $fillable = [
        'Student_id',
        'Profile',
    ];

    /**
     * 🔗 Relationship: each profile belongs to one student
     */
    public function student()
    {
        return $this->belongsTo(StudentManage::class, 'Student_id', 'Student_id');
    }
}
