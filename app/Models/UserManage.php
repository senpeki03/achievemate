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

    // 🔹 Para automatic kasama sa JSON / attributes
    protected $appends = ['full_name'];

    public function userDesignations()
    {
        return $this->hasMany(\App\Models\UserDesignation::class, 'User_id', 'User_id');
    }

    public function getFullNameAttribute(): string
    {
        $mi    = $this->Middle_name ? (' ' . mb_substr($this->Middle_name, 0, 1) . '.') : '';
        $title = $this->Title ? ($this->Title . ' ') : '';

        return trim($title . $this->First_name . $mi . ' ' . $this->Last_name);
    }
}
