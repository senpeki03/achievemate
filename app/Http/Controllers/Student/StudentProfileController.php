<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\StudentManage;
use App\Models\GraduationForm;
use App\Models\Profile;
use App\Models\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;

class StudentProfileController extends Controller
{
    public function index()
    {
        $studentId = session('student_id') ?? session('Student_id');
        abort_if(!$studentId, 403, 'No active student session.');

        $student = StudentManage::with([
            'curriculum.curriculumAy.college',
            'curriculum.curriculumAy.program',
        ])->findOrFail($studentId);

        $gradForm = GraduationForm::where('Student_id', $studentId)->first();

        $fullName = trim(
            ($student->First_name ?? '') . ' ' .
            ($student->Middle_name ?? '') . ' ' .
            ($student->Last_name  ?? '')
        ) ?: 'Student';

        $college = $student->curriculum?->curriculumAy?->college?->College_name ?? '—';
        $program = $student->curriculum?->curriculumAy?->program?->Program_name ?? '—';
        $ayLabel = $student->curriculum?->curriculumAy?->Academic_year ?? '—';

        $contactNumber = $student->Contact ?? '';
        $placeOfBirth  = $gradForm->PlaceofBirth ?? '';
        $homeAddress   = $gradForm->HomeAddress ?? '';

        $dobYmd = '';
        if (!empty($gradForm?->Birthdate)) {
            try {
                $dobYmd = Carbon::parse($gradForm->Birthdate)->format('Y-m-d');
            } catch (\Throwable $e) {
                // ignore parse error
            }
        }

        // Streamed image URL (with cache-buster)
        $photoUrl = route('student.profile.photo', ['_v' => time()]);

        return view('student.profile', compact(
            'student',
            'fullName',
            'college',
            'program',
            'ayLabel',
            'contactNumber',
            'placeOfBirth',
            'homeAddress',
            'dobYmd',
            'photoUrl'
        ));
    }

    /**
     * Stream the stored profile image (or 1x1 png if none)
     */
    public function photo()
    {
        $studentId = session('student_id') ?? session('Student_id');
        abort_if(!$studentId, 403);

        $row = Profile::where('Student_id', $studentId)->first();

        if (!$row || empty($row->Profile)) {
            // 1x1 transparent PNG
            $png1x1 = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgYAAAAAMAASsJTYQAAAAASUVORK5CYII=');
            return response($png1x1, 200)->header('Content-Type', 'image/png');
        }

        $info = @getimagesizefromstring($row->Profile);
        $mime = $info['mime'] ?? 'image/jpeg';

        return response($row->Profile, 200)->header('Content-Type', $mime);
    }

    /**
     * Save profile photo when the user clicks "Edit Profile".
     * Photo is optional—if not provided, we keep existing one.
     */
    public function save(Request $request)
    {
        $studentId = session('student_id') ?? session('Student_id');
        abort_if(!$studentId, 403);

        try {
            // If there is a file, validate it. If none, skip (no change).
            if ($request->hasFile('photo')) {
                $request->validate([
                    'photo' => ['image', 'max:5120'], // 5MB
                ]);

                $file = $request->file('photo');
                if (!$file || !$file->isValid()) {
                    throw ValidationException::withMessages(['photo' => 'Invalid upload.']);
                }

                $bytes = file_get_contents($file->getRealPath());

                Profile::updateOrCreate(
                    ['Student_id' => $studentId],
                    ['Profile'    => $bytes]
                );
            }

            return response()->json(['ok' => true, 'message' => 'Profile saved.']);

        } catch (ValidationException $e) {
            return response()->json([
                'ok'      => false,
                'message' => $e->getMessage(),
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            \Log::error('Profile save failed', ['err' => $e->getMessage()]);
            return response()->json(['ok' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Change student password (AJAX).
     *
     * Uses Login table via StudentManage->Login_id.
     */
    public function changePassword(Request $request)
    {
        $studentId = session('student_id') ?? session('Student_id');
        abort_if(!$studentId, 403, 'No active student session.');

        try {
            // Validate inputs
            $validated = $request->validate([
                'current_password'          => ['required'],
                'new_password'              => ['required', 'min:8', 'confirmed'], // needs new_password_confirmation
            ]);

            // Get student + its login record
            $student = StudentManage::findOrFail($studentId);

            if (!$student->Login_id) {
                throw ValidationException::withMessages([
                    'current_password' => ['No login account linked to this student.'],
                ]);
            }

            $login = Login::findOrFail($student->Login_id);

            // password column in login table
            $storedPassword = $login->password ?? '';

            // Accept both hashed & legacy plain-text passwords
            $currentOk =
                Hash::check($validated['current_password'], $storedPassword) ||
                $validated['current_password'] === $storedPassword;

            if (!$currentOk) {
                throw ValidationException::withMessages([
                    'current_password' => ['Current password is incorrect.'],
                ]);
            }

            // Save new password (hashed) in login table
            $login->password = Hash::make($validated['new_password']);
            $login->save();

            return response()->json([
                'ok'      => true,
                'message' => 'Password changed successfully.',
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'ok'      => false,
                'message' => 'Validation error.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            \Log::error('Change password failed', [
                'student_id' => $studentId,
                'err'        => $e->getMessage(),
            ]);

            return response()->json([
                'ok'      => false,
                'message' => 'Server error: ' . $e->getMessage(),
            ], 500);
        }
    }
}
