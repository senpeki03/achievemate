<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\Login;

class UserListController extends Controller
{
    public function index()
    {
        $students = DB::table('studentmanagement')
            ->select('department', DB::raw('COUNT(*) as student_count'))
            ->groupBy('department')
            ->orderBy('department')
            ->get();

        $users = DB::table('usermanagement')
            ->join('login', 'usermanagement.Login_id', '=', 'login.Login_id')
            ->select('login.usertype', DB::raw('COUNT(*) as count'))
            ->groupBy('login.usertype')
            ->orderBy('login.usertype')
            ->get();

        return view('admin.userlist', compact('students', 'users'));
    }

    public function showYearList($department)
    {
        $students = DB::table('studentmanagement')
            ->select('year', DB::raw('COUNT(*) as student_count'))
            ->where('department', $department)
            ->groupBy('year')
            ->orderBy('year')
            ->get();

        return view('admin.list', compact('students', 'department'));
    }

    public function showStudentsByYear($department, $year)
    {
        if ($department === 'CICS' && in_array($year, ['Third Year', 'Fourth Year'])) {
            $tracks = DB::table('studentmanagement')
                ->select('track', DB::raw('COUNT(*) as student_count'))
                ->where('department', $department)
                ->where('year', $year)
                ->whereNotNull('track')
                ->groupBy('track')
                ->get();

            return view('admin.tracksummary', compact('department', 'year', 'tracks'));
        }

        // Default: show students directly
        $students = DB::table('studentmanagement')
            ->where('department', $department)
            ->where('year', $year)
            ->get();

        return view('admin.collegeuserlist', compact('students', 'department', 'year'));
    }


    public function showUserTypeList($usertype)
    {
        $users = DB::table('usermanagement')
            ->join('login', 'usermanagement.Login_id', '=', 'login.Login_id')
            ->where('login.usertype', $usertype)
            ->select('usermanagement.*', 'login.usertype')
            ->get();

        return view('admin.proflist', compact('users', 'usertype'));
    }

    public function showStudentsByTrack($department, $year, $track)
    {
        $students = DB::table('studentmanagement')
            ->where('department', $department)
            ->where('year', $year)
            ->where('track', $track)
            ->get();

        return view('admin.collegeuserlist', compact('students', 'department', 'year', 'track'));
    }


    public function destroy($id)
    {
        $student = Student::findOrFail($id);
        $loginId = $student->Login_id;
        $student->delete();

        if ($loginId) {
            Login::where('Login_id', $loginId)->delete();
        }

        return response()->json(['success' => true]);
    }
}

