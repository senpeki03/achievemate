<?php

namespace App\Http\Controllers\registrar;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RegistrarController extends Controller
{
    public function index()
    {
        return view('registrar.layout'); // Or student.dashboard if that's your file
    }
}
