<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

use App\Models\Latin;
use App\Models\GraduationForm;
// If your student table/model is named differently, just fix this import:
use App\Models\StudentManage;

class LatinController extends Controller
{
    /**
     * Optional: route to render the page
     */
    public function page()
    {
        return view('student.latin'); // resources/views/student/latin.blade.php
    }

    /**
     * Store (or upsert) the consent record into `latin`.
     * The server derives Student_id and GraduationForm_id.
     */
    public function store(Request $request)
    {
        // Optional validation. Consent defaults to 1.
        $request->validate([
            'consent' => 'nullable|in:0,1'
        ]);

        $user = Auth::user();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'Unauthenticated'], 401);
        }

        // 1) Resolve Student_id from logged-in user.
        // Adjust this mapping if your user <-> student relation is different.
        // Common pattern: StudentManage has Login_id that matches users.id or users.Login_id.
        $loginKey = $user->Login_id ?? $user->id; // be tolerant
        $student = StudentManage::where('Login_id', $loginKey)->first();
        if (!$student) {
            return response()->json(['ok' => false, 'message' => 'Student record not found for this account'], 404);
        }
        $studentId = $student->Student_id;

        // 2) Resolve latest GraduationForm for this Student
        $grad = GraduationForm::where('Student_id', $studentId)
            ->orderByDesc('GraduationForm_id')
            ->first();

        if (!$grad) {
            return response()->json(['ok' => false, 'message' => 'No Graduation Form found for this student'], 404);
        }

        $graduationFormId = $grad->GraduationForm_id;
        $consentValue = (int)($request->input('consent', 1)); // default to consent=1

        // 3) Upsert by (Student_id, GraduationForm_id)
        $latin = Latin::updateOrCreate(
            [
                'Student_id'        => $studentId,
                'GraduationForm_id' => $graduationFormId,
            ],
            [
                'Consent'           => $consentValue,
            ]
        );

        return response()->json([
            'ok' => true,
            'latin_id' => $latin->Latin_id,
            'Student_id' => $studentId,
            'GraduationForm_id' => $graduationFormId,
            'Consent' => $latin->Consent,
        ], 201);
    }

    /**
     * (You likely already have a generator endpoint elsewhere.)
     * This is just a placeholder signature to show where it lives if needed.
     */
    // public function generate(Request $request) { ... }
}
