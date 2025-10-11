<?php

namespace App\Http\Controllers\Dean;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\Controller;

class DeanSidebarController extends Controller
{
    public function index() {
        $currentRoute = Route::currentRouteName();  // kunin active route name
        return view('dean.deansidebar', compact('currentRoute'));
    }

}
