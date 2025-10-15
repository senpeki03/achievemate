<?php

namespace App\Http\Controllers\Dean;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\College;
use App\Models\Program;
use App\Models\UserDesignation;
use App\Models\Application;

class HonorListController extends Controller
{
    public function index()
    {
        $authUser = auth()->user();
        if (!$authUser) {
            return redirect()->route('login')->with('error', 'Please login to access this page.');
        }

        $userDesignation = UserDesignation::where('Login_id', $authUser->Login_id)->first();

        if (!$userDesignation || !$userDesignation->College_id || !$userDesignation->Program_id) {
            return view('dean.honorlist', [
                'colleges' => collect([]),
                'programs' => collect([]),
                'students' => collect([]),
            ]);
        }

        $college = College::find($userDesignation->College_id);
        $program = Program::find($userDesignation->Program_id);

        return view('dean.honorlist', [
            'colleges' => collect([$college]),
            'programs' => collect([$program]),
            'students' => collect([]),
        ]);
    }

    public function getProgramsByCollege($collegeId)
    {
        $authUser = auth()->user();
        $userDesignation = UserDesignation::where('Login_id', $authUser->Login_id)->first();

        if (!$userDesignation || !$userDesignation->Program_id) {
            return response()->json(['message' => 'No designated program found for this user.'], 404);
        }

        $program = Program::where('Program_id', $userDesignation->Program_id)
            ->where('College_id', $collegeId)
            ->first();

        if (!$program) {
            return response()->json(['message' => 'Program not found under this college.'], 404);
        }

        return response()->json([[
            'Program_id'   => $program->Program_id,
            'Program_name' => $program->Program_name,
        ]], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Return only Program Chair–verified (Status = 'Verified') or already Approved applications.
     * Include GWA and Rank; sort by GWA (asc) server-side as a sensible default.
     */
    public function getStudentsByProgram($programId)
    {
        $apps = Application::with('student.curriculum.curriculumAy.program')
            ->whereHas('student.curriculum.curriculumAy', function ($q) use ($programId) {
                $q->where('Program_id', $programId);
            })
            ->whereIn('Status', ['For Approval', 'Approved'])
            ->orderBy('GWA')                // table has no created_at, so sort by GWA first
            ->orderBy('Application_id')     // stable tie-breaker
            ->get();

        $students = $apps->map(function ($app) {
            $s = $app->student;
            $full = trim(
                ($s->First_name ?? '') . ' ' .
                ($s->Middle_name ?? '') . ' ' .
                ($s->Last_name ?? '')
            );

            return [
                'application_id' => $app->Application_id,
                'student_id'     => $app->Student_id,
                'fullname'       => $full,
                'year_level'     => $s->Year ?? '',
                'status'         => $app->Status ?? 'Pending',
                'gwa'            => $app->GWA,
                'rank'           => $app->Rank,   // already computed by your process
            ];
        })->values();

        return response()->json($students, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function viewFile($id)
    {
        $application = Application::findOrFail($id);

        return response($application->File_data, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$application->File_name.'"');
    }

    /**
     * Dean can only move Verified -> Approved (or re-approve).
     */
    public function updateStatus(Request $request)
    {
        $request->validate([
            'id'     => 'required|integer|exists:application,Application_id',
            'status' => 'required|in:Approved',
        ]);

        $app = Application::find($request->id);
        if (!$app) {
            return response()->json(['message' => 'Application not found'], 404);
        }

        if (!in_array($app->Status, ['For Approval', 'Approved'], true)) {
            return response()->json([
                'message' => 'Only applications endorsed by the Program Chair can be approved.'
            ], 422);
        }

        $app->Status = 'Approved';
        $app->save();

        return response()->json(['message' => 'Status updated successfully']);
    }
}
