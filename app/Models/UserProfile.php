<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    protected $table = 'userprofile';
    protected $primaryKey = 'Userprofile_id';
    public $timestamps = false;

    protected $fillable = ['User_id','Profile'];
    protected $casts = ['Profile' => 'string'];
}
