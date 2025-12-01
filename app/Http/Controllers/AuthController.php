<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Login;
use App\Models\StudentManage;
use App\Models\UserDesignation;
use App\Models\UserManage;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required',
        ]);

        $user = Login::where('username', $request->username)->first();

        if ($user && Hash::check($request->password, $user->password)) {
            Auth::loginUsingId($user->Login_id);
            $user = Auth::user();

            session([
                'Login_id' => $user->Login_id,
                'username' => $user->username,
                'usertype' => $user->usertype,
            ]);

            // Handle session values based on usertype
            if ($user->usertype === 'Student') {
                $student = StudentManage::where('Login_id', $user->Login_id)->first();

                if ($student) {
                    session([
                        'Student_id' => $student->Student_id, 
                        'SRCODE'      => $student->SRCODE,
                        'First_name'  => $student->First_name,
                        'Middle_name' => $student->Middle_name,
                        'Last_name'   => $student->Last_name,
                        'Contact'     => $student->Contact,
                        'Email'       => $student->Email,
                    ]);
                }

            } elseif ($user->usertype === 'Admin') {
                $admin = UserManage::where('Email', 'admin@example.com')->first();

                if ($admin) {
                    session([
                        'First_name' => $admin->First_name,
                        'Last_name'  => $admin->Last_name,
                    ]);

                    Log::info('✅ SESSION SET FOR ADMIN:', [
                        'Login_id'    => session('Login_id'),
                        'First_name'  => session('First_name'),
                        'Last_name'   => session('Last_name'),
                        'usertype'    => session('usertype'),
                    ]);
                } else {
                    Log::error('❌ Admin not found in user_manage!');
                }

            } elseif ($user->usertype === 'Registrar') {
                $registrar = UserManage::where('Email', 'registrar@example.com')->first();

                if ($registrar) {
                    session([
                        'First_name' => $registrar->First_name,
                        'Last_name'  => $registrar->Last_name,
                    ]);

                    Log::info('✅ SESSION SET FOR REGISTRAR:', [
                        'Login_id'    => session('Login_id'),
                        'First_name'  => session('First_name'),
                        'Last_name'   => session('Last_name'),
                        'usertype'    => session('usertype'),
                    ]);
                } else {
                    Log::error('❌ Registrar not found in user_manage!');
                }

            } else {
                // Default: Faculty/Chair/Other users via user_designation
                $userDetails = UserDesignation::with('user')->where('Login_id', $user->Login_id)->first();

                if ($userDetails && $userDetails->user) {
                    session([
                        'First_name' => $userDetails->user->First_name,
                        'Last_name'  => $userDetails->user->Last_name,
                    ]);
                }
            }

            // Redirect based on usertype
            switch ($user->usertype) {
                case 'Admin':
                    return redirect('/admin/campus');
                case 'Registrar':
                    return redirect('/registrar/dashboard');
                case 'Student':
                    return redirect('/student/dashboard');
                case 'Program Chairperson':
                    return redirect()->route('programchair.dashboard');
                case 'Dean':
                    return redirect()->route('dean.dashboard');
                case 'Vice Chancellor for Academic Affairs':
                    return redirect()->route('vcaa.dashboard');
                case 'user':
                    return redirect('/user-home');
                default:
                    return redirect('/');
            }
        }

        return back()->with('error', 'Invalid credentials.');
    }

    public function logout()
    {
        if (session()->has('srcode')) {
            $student = StudentManage::where('SRCODE', session('srcode'))->first();

            if ($student) {
                \App\Models\Logs::record($student->userId, 'logout_success');
            }
        }

        session()->flush();
        Auth::logout();
        return redirect('/');
    }
}
