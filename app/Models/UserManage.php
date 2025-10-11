<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserManage extends Model
{
    protected $table = 'user_manage';
    protected $primaryKey = 'User_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'Title',
        'First_name',
        'Middle_name',
        'Last_name',
        'Email',
    ];

    public function userDesignations()
    {
        return $this->hasMany(\App\Models\UserDesignation::class, 'User_id', 'User_id');
    }

    
}
