<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\StudentManage;
use App\Models\Login;

class StudentListController extends Controller
{
    public function index()
    {
        $students = StudentManage::paginate(10);
        return view('registrar.student.list', compact('students'));
    }

    public function destroy($id)
    {
        $student = StudentManage::where('Student_id', $id)->firstOrFail();
        $loginId = $student->Login_id;

        $student->delete();

        if ($loginId) {
            Login::where('Login_id', $loginId)->delete();
        }

        return response()->json(['success' => true]);
    }


}

