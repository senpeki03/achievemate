<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\StudentManage;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\UserManage;

class DashboardRegistrarController extends Controller
{
    public function index()
    {
        $totalUsers = UserManage::count();
        $loginCount = DB::table('system_logs')->where('action', 'login_success')->count();
        $logoutCount = DB::table('system_logs')->where('action', 'logout_success')->count();

        // Departments to show
        $departments = ['CICS', 'CTE', 'CONAHS', 'CAS', 'CABEIHM', 'CCJE'];

        // Initialize department counts
        $departmentCounts = [];
        foreach ($departments as $dept) {
            $departmentCounts[$dept] = StudentManage::whereHas('curriculum.curriculumAy.college', function ($query) use ($dept) {
                $query->where('College_name', $dept); // Ensure the correct column name (e.g., 'name', 'department_name')
            })->count() ?: 0;  // Default to 0 if no students are found
        }

        // Example metrics
        $totalStudents = StudentManage::count();

        // Pass the variables to the view
        return view('registrar.dashboard', compact(
            'departments', // Pass the departments array to the view
            'departmentCounts', // Pass the department counts to the view
            'totalUsers',
            'loginCount',
            'logoutCount',
            'totalStudents',
        ));
    }
}
