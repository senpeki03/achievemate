<?php

namespace App\Http\Controllers\admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Usermanagement;
use App\Models\Login;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class UserImportController extends Controller
{
    public function import(Request $request)
    {
        $request->validate([
            'csv' => 'required|file|mimes:csv,txt'
        ]);

        $file = $request->file('csv');
        $rawContent = str_replace("\xEF\xBB\xBF", '', file_get_contents($file));
        $data = array_map('str_getcsv', explode("\n", trim($rawContent)));

        $header = array_map(fn($h) => strtolower(str_replace(' ', '_', trim($h))), $data[0]);
        unset($data[0]);

        foreach ($data as $index => $row) {
            if (count($row) < count($header)) continue;

            $row = array_combine($header, $row);
            $email = trim($row['email'] ?? '');
            $srcode = trim($row['srcode'] ?? '');
            $usertype = trim($row['usertype'] ?? '');

            if (empty($email) || empty($srcode)) continue;

            // Check for duplicate email
            if (Usermanagement::where('email', $email)->exists()) {
                return response()->json(['duplicate' => true], 409);
            }

            try {
                $username = $srcode . '@g.batstate-u.edu.ph';
                $defaultPassword = $srcode;

                $login = Login::create([
                    'username' => $username,
                    'password' => bcrypt($defaultPassword),
                    'usertype' => $usertype
                ]);

                Usermanagement::create([
                    'login_id'   => $login->Login_id,
                    'Srcode'     => $srcode,
                    'firstname'  => $row['firstname'] ?? '',
                    'middlename' => $row['middlename'] ?? '',
                    'lastname'   => $row['lastname'] ?? '',
                    'email'      => $email
                ]);

                Mail::raw("
                    Dear User,

                    Welcome to AchieveMate!

                    Your account has been created. Here are your credentials:

                    Username: $username
                    Password: $defaultPassword
                    User Type: $usertype

                    Please log in and change your password immediately.

                    Thank you,  
                    AchieveMate Team
                ", function ($message) use ($email) {
                    $message->to($email)->subject('Your Login Credentials');
                });

                session()->put('email_sent', true);
                return response()->json(['success' => true]);

            } catch (\Exception $e) {
                Log::error("Import error on row $index: " . $e->getMessage());
                return response()->json(['error' => 'Server error.'], 500);
            }
        }

        return response()->json(['error' => 'No valid user record to import.'], 400);
    }
}
