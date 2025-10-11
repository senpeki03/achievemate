<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AdminSidebarController extends Controller
{
    public function show()
    {
        return view('admin.adminsidebar');
    }
}
