<?php

namespace App\Http\Controllers\Programchair;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\College;
use App\Models\Program;
use App\Models\UserDesignation;
use App\Models\Application;

class DeansHonorListController extends Controller
{
    public function index()
    {
        $authUser = auth()->user();
        if (!$authUser) {
            return redirect()->route('login')->with('error', 'Please login to access this page.');
        }

        $userDesignation = UserDesignation::where('Login_id', $authUser->Login_id)->first();

        if (!$userDesignation || !$userDesignation->College_id || !$userDesignation->Program_id) {
            return view('programchair.deanshonorlist', [
                'colleges' => [],
                'programs' => [],
                'students' => [],
            ]);
        }

        $college = College::find($userDesignation->College_id);
        $program = Program::find($userDesignation->Program_id);

        return view('programchair.deanshonorlist', [
            'colleges' => collect([$college]),
            'programs' => collect([$program]),
            'students' => [], // JS loads per program
        ]);
    }

    public function getProgramsByCollege($collegeId)
    {
        $authUser = auth()->user();
        $userDesignation = UserDesignation::where('Login_id', $authUser->Login_id)->first();

        if (!$userDesignation || !$userDesignation->Program_id) {
            return response()->json([], 200);
        }

        $program = Program::where('Program_id', $userDesignation->Program_id)
            ->where('College_id', $collegeId)
            ->first();

        return response()->json($program ? [[
            'Program_id'   => $program->Program_id,
            'Program_name' => $program->Program_name,
        ]] : []);
    }

    public function getStudentsByProgram($programId)
    {
        $apps = Application::with(['student.curriculum.curriculumAy.program'])
            // ->where('Type', "Dean's List") // enable if you tag by type
            ->whereHas('student.curriculum.curriculumAy', function ($q) use ($programId) {
                $q->where('Program_id', $programId);
            })
            ->get();

        // Sort by GWA asc, then name
        $sorted = $apps->sortBy(function ($app) {
            $gwaNum = is_numeric($app->GWA) ? (float)$app->GWA : INF;
            $ln = strtoupper(trim($app->student->Last_name ?? ''));
            $fn = strtoupper(trim($app->student->First_name ?? ''));
            return [$gwaNum, $ln, $fn];
        })->values();

        $students = $sorted->map(function ($app) {
            $s = $app->student;
            $fullname = trim(($s->First_name ?? '').' '.(($s->Middle_name ?? '') ? $s->Middle_name.' ' : '').($s->Last_name ?? ''));
            return [
                'application_id' => $app->Application_id,
                'fullname'       => $fullname,
                'year_level'     => $s->Year,
                'status'         => $app->Status ?? 'Pending',
                'gwa'            => $app->GWA,
                'rank'           => $app->Rank, // if null, UI computes fallback
            ];
        });

        return response()->json($students);
    }

    public function viewFile($id)
    {
        $application = Application::findOrFail($id);

        return response($application->File_data, 200)
            ->header('Content-Type', 'application/pdf')
            ->header(
                'Content-Disposition',
                'inline; filename="'.($application->File_name ?? "application-$id.pdf").'"'
            );
    }

    public function updateStatus(Request $request)
    {
        // FIX 1: correct table name in exists rule
        // FIX 2: restrict Program Chair to setting only "Verified"
        $request->validate([
            'id'     => 'required|integer|exists:application,Application_id',
            'status' => 'required|in:For Approval',
        ]);

        $application = Application::find($request->id);
        if (!$application) {
            return response()->json(['message' => 'Application not found'], 404);
        }

        $application->Status = 'For Approval';
        $application->save();

        return response()->json(['ok' => true, 'message' => 'Status updated successfully']);
    }

    public function bulkVerify(Request $request)
    {
        $data = $request->validate([
            'application_ids'   => 'required|array|min:1',
            'application_ids.*' => 'integer|exists:application,Application_id',
        ]);

        Application::whereIn('Application_id', $data['application_ids'])
            ->update(['Status' => 'For Approval']);

        return response()->json(['ok' => true, 'message' => 'Selected applications verified.']);
    }
}
