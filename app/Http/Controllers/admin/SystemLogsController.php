<?php

namespace App\Http\Controllers\admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class SystemLogsController extends Controller
{
    public function index()
    {
    $logs = DB::table('system_logs')
    ->leftJoin('studentmanagement', 'system_logs.userId', '=', 'studentmanagement.userId')
    ->select(
        'system_logs.userId',
        'studentmanagement.firstname',
        'studentmanagement.middlename',
        'studentmanagement.lastname',
        'system_logs.action',
        'system_logs.ip_address',
        'system_logs.created_at'
    )
    ->orderBy('system_logs.created_at', 'desc')
    ->get();


        return view('admin.systemlogs', compact('logs'));
    }
}
