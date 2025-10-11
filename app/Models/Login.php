<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

class Login extends Authenticatable implements AuthenticatableContract
{
    protected $table = 'login';
    public $timestamps = false;
    protected $primaryKey = 'Login_id';
    public $incrementing = true;
    protected $keyType = 'int';
    protected $with = ['userDesignation'];

    protected $fillable = ['username', 'password', 'usertype'];

    public function userDesignation()
    {
        return $this->hasOne(\App\Models\UserDesignation::class, 'Login_id', 'Login_id');
    }

        public function getAuthIdentifierName()
    {
        return 'Login_id';
    }

    
    public function getAuthIdentifier()
    {
        return $this->Login_id;
    }
}
