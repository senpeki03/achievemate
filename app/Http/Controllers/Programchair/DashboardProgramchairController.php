<?php

namespace App\Http\Controllers\Programchair;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\StudentManage;
use App\Models\College;
use App\Models\Program;

class DashboardProgramchairController extends Controller
{
    public function index()
    {
        return view('programchair.dashboard');
    }

}
