<?php

namespace App\Http\Controllers\Registrar;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Login;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class StudentImportController extends Controller
{
    public function import(Request $request)
    {
        $request->validate([
            'csv' => 'required|file|mimes:csv,txt'
        ]);

        $file = $request->file('csv');
        $rawContent = str_replace("\xEF\xBB\xBF", '', file_get_contents($file));
        $data = array_map('str_getcsv', explode("\n", trim($rawContent)));

        $header = array_map(function ($h) {
            return strtolower(str_replace(' ', '_', trim($h)));
        }, $data[0]);
        unset($data[0]);

        foreach ($data as $index => $row) {
            if (count($row) < count($header)) continue;

            $row = array_combine($header, $row);
            $email = trim($row['email'] ?? '');
            $srcode = trim($row['srcode'] ?? '');

            if (empty($email) || empty($srcode)) continue;

            if (DB::table('studentmanagement')->where('email', $email)->exists()) {
                return response()->json(['duplicate' => true], 409); // Email already taken
            }

            try {
                $username = $srcode . '@g.batstate-u.edu.ph';
                $defaultPassword = $srcode;

                $login = Login::create([
                    'username' => $username,
                    'password' => bcrypt($defaultPassword),
                    'usertype' => 'Student'
                ]);

                Student::create([
                    'login_id'   => $login->Login_id,
                    'srcode'     => $srcode,
                    'firstname'  => $row['firstname'] ?? '',
                    'middlename' => $row['middlename'] ?? '',
                    'lastname'   => $row['lastname'] ?? '',
                    'Year'       => $row['year'] ?? '',
                    'department' => $row['department'] ?? '',
                    'program'    => $row['program'] ?? '',
                    'track'      => $row['track'] ?? '',
                    'contact'    => $row['contact'] ?? '',
                    'email'      => $email
                ]);

                // ✅ Mail::raw implementation
                Mail::raw("
                    Dear User,

                    Welcome to AchieveMate!

                    Your account has been created. Here are your credentials:

                    Username: $username
                    Password: $defaultPassword
                    User Type: Student

                    Please log in and change your password immediately.

                    Thank you,  
                    AchieveMate Team
                ", function ($message) use ($row) {
                    $message->to($row['email'] ?? '')
                            ->subject('Your Login Credentials');
                });

                session()->put('email_sent', true);
                return response()->json(['success' => true]);

            } catch (\Exception $e) {
                Log::error("Import error on row $index: " . $e->getMessage());
                return response()->json(['error' => 'Server error.'], 500);
            }
        }

        return response()->json(['error' => 'No valid student record to import.'], 400);
    }

        public function create()
    {
        return view('registrar.addstudent');
    }

    public function store(Request $request)
    {
    $request->validate([
        'srcode' => 'required|unique:studentmanagement,srcode',
        'firstname' => 'required',
        'lastname' => 'required',
        'year' => 'required',
        'department' => 'required',
        'program' => 'required',
        'contact' => 'required',
        'email' => 'required|email|unique:studentmanagement,email'
    ]);

    try {
        $username = $request->srcode . '@g.batstate-u.edu.ph';
        $defaultPassword = $request->srcode;

        // ✅ Create Login
        $login = Login::create([
            'username' => $username,
            'password' => bcrypt($defaultPassword),
            'usertype' => 'Student'
        ]);

        // ✅ Create Student
        Student::create([
            'login_id'   => $login->Login_id,
            'srcode'     => $request->srcode,
            'firstname'  => $request->firstname,
            'middlename' => $request->middlename,
            'lastname'   => $request->lastname,
            'Year'       => $request->year,
            'department' => $request->department,
            'program'    => $request->program,
            'track'      => $request->track,
            'contact'    => $request->contact,
            'email'      => $request->email
        ]);

        // ✅ Send Welcome Email
        Mail::raw("
            Dear {$request->firstname},

            Welcome to AchieveMate!

            Your student account has been created.

            Username: $username
            Password: $defaultPassword
            User Type: Student

            Please log in and change your password immediately.

            Regards,
            AchieveMate Team
        ", function ($message) use ($request) {
            $message->to($request->email)
                    ->subject('Welcome to AchieveMate - Your Login Credentials');
        });

        Session::flash('success', 'Student added successfully and login credentials emailed.');
        return redirect()->route('student.management.index');


    } catch (\Exception $e) {
        Log::error('Error creating student: ' . $e->getMessage());
        return back()->withErrors(['error' => 'Failed to add student.']);
    }
    }

}
