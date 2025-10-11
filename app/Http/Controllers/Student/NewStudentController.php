<?php

namespace App\Http\Controllers\student;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\Controller;

class NewStudentController extends Controller
{
    public function index() {
        $currentRoute = Route::currentRouteName();  // kunin active route name
        return view('student.studentsidebar', compact('currentRoute'));
    }

}
