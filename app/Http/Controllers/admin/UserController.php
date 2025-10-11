<?php

namespace App\Http\Controllers\admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class UserController extends Controller
{
    public function index()
    {
        return view('admin.adminlayout'); // Or student.dashboard if that's your file
    }
}
