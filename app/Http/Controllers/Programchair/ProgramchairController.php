<?php

namespace App\Http\Controllers\Programchair;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProgramchairController extends Controller
{
    public function index()
    {
        return view('programchair.programchairsidebar'); // Or student.dashboard if that's your file
    }
}
