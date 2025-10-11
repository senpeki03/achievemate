<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Application;

class ApplicationRedirectController extends Controller
{
    public function handle()
    {
        $studentId = session('Student_id');

        if (!$studentId) {
            return redirect()->route('login'); // Redirect to login if student is not logged in
        }

        // Check if the student has an existing application
        $application = Application::where('Student_id', $studentId)->first();

        // Redirect to the application status page if an application exists
        if ($application) {
            return redirect()->route('student.application.status');
        }

        // If no application exists, redirect to the application form
        return redirect()->route('student.application.form');
    }
}
