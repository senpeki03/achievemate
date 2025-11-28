<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\GraduationApplication;
use App\Models\GraduationApplicationRequirement;
use App\Models\StudentManage;
use App\Models\StudentCourse;
use App\Models\Program;
use App\Models\Major;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class GraduationApplicationController extends Controller
{
    /**
     * GET /student/apply
     * Create (or fetch) the student's draft application and show the form.
     */
    public function create()
    {
        // 🔹 Login_id ng kasalukuyang user
        $loginId = Auth::id();

        // GraduationApplication.Student_id = pwede mo pa ring gamitin as login_id
        $app = GraduationApplication::firstOrCreate(
            ['Student_id' => $loginId, 'status' => 'draft'],
            []
        );

        // 🔹 Build prefill (including College, Program, Major from student_course)
        $prefill = $this->buildPrefillForStudent($loginId);

        return view('student.apply', [
            'app'                 => $app,
            'prefill'             => $prefill,
            'isApplicationClosed' => false, // or your own flag
        ]);
    }

    /**
     * GET /student/graduation/my-application
     * Reuse the same apply page with the latest app.
     */
    public function show()
    {
        $loginId = Auth::id();

        $app = GraduationApplication::where('Student_id', $loginId)
            ->latest('Graduation_id')
            ->first();

        if (! $app) {
            $app = GraduationApplication::create([
                'Student_id' => $loginId,
                'status'     => 'draft',
            ]);
        }

        $prefill = $this->buildPrefillForStudent($loginId);

        return view('student.apply', [
            'app'                 => $app,
            'prefill'             => $prefill,
            'isApplicationClosed' => false,
        ]);
    }

    /**
     * Build all prefill values for the form (including college/program/major).
     *
     * @param  int  $loginId   Auth::id() ng kasalukuyang user
     */
        protected function buildPrefillForStudent(int $loginId): array
        {
            // hanapin si student gamit Login_id (ito yung usual pattern sa system mo)
            $student = StudentManage::where('Login_id', $loginId)->first();

            $prefill = [
                'surname'           => '',
                'first_name'        => '',
                'middle_name'       => '',
                'ext'               => '',
                'sr_code'           => '',
                'birthdate'         => '',
                'place_of_birth'    => '',
                'contact_number'    => '',
                'email'             => '',
                'scholarship_grant' => '',
                'parent1'           => '',
                'parent1_contact'   => '',
                'parent2'           => '',
                'parent2_contact'   => '',
                'zip_code'          => '',
                'home_address'      => '',
                'secondary_school'  => '',
                'secondary_year'    => '',
                'elementary_school' => '',
                'elementary_year'   => '',
                'college'           => '',
                'program'           => '',
                'major'             => '',
            ];

            if (! $student) {
                return $prefill;
            }

            $studentId = $student->Student_id ?? null;

            // basic info
            $birthFormatted = '';
            if (! empty($student->Birthdate)) {
                if ($student->Birthdate instanceof \Carbon\Carbon) {
                    $birthFormatted = $student->Birthdate->format('Y-m-d');
                } else {
                    $birthFormatted = date('Y-m-d', strtotime($student->Birthdate));
                }
            }

            $prefill['surname']        = $student->LastName      ?? '';
            $prefill['first_name']     = $student->FirstName     ?? '';
            $prefill['middle_name']    = $student->MiddleName    ?? '';
            $prefill['ext']            = $student->NameExtension ?? '';
            $prefill['sr_code']        = $student->SRCODE        ?? '';
            $prefill['birthdate']      = $birthFormatted;
            $prefill['place_of_birth'] = $student->PlaceOfBirth  ?? '';
            $prefill['contact_number'] = $student->ContactNumber ?? '';
            $prefill['email']          = $student->Email         ?? '';

            // course info via relations
            if ($studentId) {
                $studentCourse = StudentCourse::with(['college','program','major'])
                    ->where('Student_id', $studentId)
                    ->first();

                if ($studentCourse) {
                    $prefill['college'] = $studentCourse->college->College_name ?? '';
                    $prefill['program'] = $studentCourse->program->Program_name ?? '';
                    $prefill['major']   = $studentCourse->major->Major_name     ?? '';
                }
            }

            return $prefill;
        }


    /**
     * POST /student/graduation/apply
     * Save basic application fields while in draft.
     */
    public function store(Request $request)
    {
        $loginId = Auth::id();

        $v = Validator::make($request->all(), [
            'college_id'        => 'nullable|integer',
            'program_id'        => 'nullable|integer',
            'application_no'    => 'nullable|string|max:100',
            'term_end'          => 'nullable|date',
            'remarks'           => 'nullable|string',
            'deficiency_notes'  => 'nullable|array',
        ]);
        if ($v->fails()) {
            return back()->withErrors($v)->withInput();
        }

        $app = GraduationApplication::firstOrCreate(
            ['Student_id' => $loginId, 'status' => 'draft'],
            []
        );

        $app->fill($request->only([
            'college_id','program_id','application_no','term_end',
            'remarks','deficiency_notes'
        ]));
        $app->Student_id = $loginId;
        $app->save();

        if ($request->expectsJson()) {
            return response()->json($app);
        }
        return redirect()->route('student.apply')->with('success', 'Saved.');
    }

    /**
     * POST /student/graduation/submit
     */
    public function submit(Request $request)
    {
        $loginId = Auth::id();

        $app = GraduationApplication::where('Student_id', $loginId)
            ->whereIn('status', ['draft', 'for_compliance'])
            ->latest('Graduation_id')
            ->firstOrFail();

        $app->status       = 'submitted';
        $app->submitted_at = now();
        $app->save();

        return $request->expectsJson()
            ? response()->json($app)
            : redirect()->route('student.apply')->with('success', 'Application submitted.');
    }

    /**
     * POST /student/graduation/resubmit
     */
    public function resubmit(Request $request)
    {
        $loginId = Auth::id();

        $app = GraduationApplication::where('Student_id', $loginId)
            ->where('status', 'for_compliance')
            ->latest('Graduation_id')
            ->firstOrFail();

        $app->status         = 'submitted';
        $app->resubmitted_at = now();
        $app->save();

        return $request->expectsJson()
            ? response()->json($app)
            : redirect()->route('student.apply')->with('success', 'Application resubmitted.');
    }

    /**
     * POST /student/graduation/upload/{req}
     */
    public function uploadRequirement(Request $request, GraduationApplicationRequirement $req)
    {
        $app = $req->application; // relation sa model
        if (! $app || (int) $app->Student_id !== (int) Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);
        $file = $data['file'];

        $dir  = "graduation/{$app->Graduation_id}";
        $name = now()->format('Ymd_His') . '_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());

        $storedPath = $file->storeAs($dir, $name, 'public');

        $req->update([
            'file_name'  => $file->getClientOriginalName(),
            'file_path'  => $storedPath,
            'mime_type'  => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'status'     => 'pending',
            'notes'      => null,
            'checked_by' => null,
            'checked_at' => null,
        ]);

        return back()->with('success', 'File uploaded.');
    }
}
