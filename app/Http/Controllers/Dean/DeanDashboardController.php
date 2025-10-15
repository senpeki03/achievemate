<?php

namespace App\Http\Controllers\Dean;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\Controller;

class DeanDashboardController extends Controller
{
    public function index() {
        return view('dean.dashboard');
    }

}
