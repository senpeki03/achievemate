<?php 

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Student;

class StudentUserListController extends Controller
{
    public function index()
    {
        // Group by department (college)
        $students = DB::table('studentmanagement')
            ->select('department', DB::raw('COUNT(*) as student_count'))
            ->whereNotNull('department')
            ->groupBy('department')
            ->orderBy('department')
            ->get();

        // Group by usertype
        $users = DB::table('usermanagement')
            ->join('login', 'usermanagement.Login_id', '=', 'login.Login_id')
            ->select('login.usertype', DB::raw('COUNT(*) as user_count'))
            ->groupBy('login.usertype')
            ->get();

        return view('registrar.userlist', compact('students', 'users'));
    }
}
