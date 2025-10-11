<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\GraduationApplication;
use App\Models\GraduationApplicationRequirement;   // ⬅️ add this
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
        $studentId = Auth::id();

        $app = GraduationApplication::firstOrCreate(
            ['Student_id' => $studentId, 'status' => 'draft'],
            [] // defaults (if any) go here
        );

        // show resources/views/student/apply.blade.php
        return view('student.apply', ['app' => $app]);
    }

    /**
     * POST /student/graduation/apply
     * Save basic application fields while in draft.
     */
    public function store(Request $request)
    {
        $studentId = Auth::id();

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
            ['Student_id' => $studentId, 'status' => 'draft'],
            []
        );

        $app->fill($request->only([
            'college_id','program_id','application_no','term_end',
            'remarks','deficiency_notes'
        ]));
        $app->Student_id = $studentId;
        $app->save();

        // Always render the same apply view you’re using
        if ($request->expectsJson()) {
            return response()->json($app);
        }
        return redirect()->route('student.apply')->with('success', 'Saved.');
    }

    /**
     * GET /student/graduation/my-application
     * (If you use this route) show the same apply page with the latest app.
     */
    public function show()
    {
        $studentId = Auth::id();

        $app = GraduationApplication::where('Student_id', $studentId)
            ->latest('Graduation_id')
            ->first();

        if (! $app) {
            $app = GraduationApplication::create([
                'Student_id' => $studentId,
                'status'     => 'draft',
            ]);
        }

        // Reuse the same Blade (student.apply)
        return view('student.apply', ['app' => $app]);
    }

    /**
     * POST /student/graduation/submit
     */
    public function submit(Request $request)
    {
        $studentId = Auth::id();

        $app = GraduationApplication::where('Student_id', $studentId)
            ->whereIn('status', ['draft', 'for_compliance'])
            ->latest('Graduation_id')
            ->firstOrFail();

        $app->status = 'submitted';
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
        $studentId = Auth::id();

        $app = GraduationApplication::where('Student_id', $studentId)
            ->where('status', 'for_compliance')
            ->latest('Graduation_id')
            ->firstOrFail();

        $app->status = 'submitted';
        $app->resubmitted_at = now();
        $app->save();

        return $request->expectsJson()
            ? response()->json($app)
            : redirect()->route('student.apply')->with('success', 'Application resubmitted.');
    }

    /**
     * POST /student/graduation/upload/{req}
     * Upload/replace a single requirement file.
     *
     * Route-model binding provides the specific requirement row.
     * Your Requirement model should have:
     *   belongsTo(GraduationApplication::class, 'Graduation_id', 'Graduation_id')
     */
    public function uploadRequirement(Request $request, GraduationApplicationRequirement $req)
    {
        // Verify ownership: the requirement must belong to current student's application
        $app = $req->application; // via relation in the model
        if (! $app || (int) $app->Student_id !== (int) Auth::id()) {
            abort(403, 'Unauthorized');
        }

        // Validate the uploaded file (10MB max; adjust as you like)
        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);
        $file = $data['file'];

        // Store under: storage/app/public/graduation/{Graduation_id}/YYYYMMDD_HHMMSS_original.ext
        $dir  = "graduation/{$app->Graduation_id}";
        $name = now()->format('Ymd_His') . '_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());

        // make sure 'public' disk is linked: php artisan storage:link
        $storedPath = $file->storeAs($dir, $name, 'public');

        // Update the requirement row
        $req->update([
            'file_name'  => $file->getClientOriginalName(),
            'file_path'  => $storedPath,                // relative to public disk
            'mime_type'  => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'status'     => 'pending',                  // reset to pending after upload
            'notes'      => null,
            'checked_by' => null,
            'checked_at' => null,
        ]);

        return back()->with('success', 'File uploaded.');
    }
}
