<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Usermanagement extends Model
{
    protected $table = 'usermanagement';
    protected $primaryKey = 'Id';
    public $timestamps = false;

    protected $fillable = [
        'login_id', 'Srcode', 'firstname', 'middlename', 'lastname', 'email'
    ];



    public function index()
    {
        $students = DB::table('studentmanagement')
            ->join('login', 'studentmanagement.Login_id', '=', 'login.Login_id')
            ->select('studentmanagement.*', 'login.usertype')
            ->get();

        return view('student.studentprofile', compact('students', 'users'));
    }

}
