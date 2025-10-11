<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
   use Illuminate\Support\Facades\DB;

class Student extends Model
{
    protected $table = 'studentmanagement'; // Important if table name is custom
    protected $primaryKey = 'userId'; // or whatever your primary key is
    public $timestamps = false; // If your table doesn't have created_at/updated_at
    protected $fillable = [
        'login_id', 'srcode', 'firstname', 'middlename' , 'lastname', 'Year','department', 'track', 'contact', 'program', 'email'
    ];

    public function student() {
    return $this->hasOne(UserManagement::class, 'Login_id', 'Login_id');
}


}
