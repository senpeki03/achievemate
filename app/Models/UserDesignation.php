<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDesignation extends Model
{
    protected $table = 'user_designation';
    protected $primaryKey = 'UserDesignation_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'Campus_id',
        'College_id',
        'Program_id',
        'Major_id',
        'Designation_id',
        'User_id',
        'Login_id',
    ];

    protected $appends = ['full_name'];
    
    public function user()
    {
        return $this->belongsTo(\App\Models\UserManage::class, 'User_id', 'User_id');
    }

    public function login()
    {
        return $this->belongsTo(Login::class, 'Login_id', 'Login_id');
    }
    
    public function designation()
    {
        return $this->belongsTo(\App\Models\Designation::class, 'Designation_id', 'Designation_id');
    }

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

    public function getFullNameAttribute(): string
    {
        $mi = $this->Middle_name ? (' ' . mb_substr($this->Middle_name, 0, 1) . '.') : '';
        $title = $this->Title ? ($this->Title . ' ') : '';
        return trim($title . $this->First_name . $mi . ' ' . $this->Last_name);
    }
}

