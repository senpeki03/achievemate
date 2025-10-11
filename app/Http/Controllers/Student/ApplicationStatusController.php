<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\PostRecipient;

class ApplicationStatusController extends Controller
{
    public function index()
    {
        $studentId = session('Student_id');
        if (!$studentId) {
            abort(403, 'Student not logged in.');
        }

        // Read applications from DB (persists across logins)
        $applications = Application::where('Student_id', $studentId)
            ->orderByDesc('Application_id')
            ->get(['Application_id','File_name','GWA','Rank','Status']);

        // If none, go straight to the Application wizard
        if ($applications->isEmpty()) {
            return redirect()
                ->route('student.application')
                ->with('info', 'You have no applications yet. Please file one.');
        }

        // Badge count for the bell icon
        $unreadCount = PostRecipient::where('Student_id', $studentId)
            ->where('is_read', false)
            ->count();

        return view('student.applicationstatus', [
            'applications' => $applications,
            'unreadCount'  => $unreadCount,
        ]);
    }

    public function destroy($id)
    {
        $studentId = session('Student_id');
        if (!$studentId) {
            return response()->json(['success' => false], 403);
        }

        $app = Application::find($id);

        if ($app && (int)$app->Student_id === (int)$studentId) {
            $app->delete();

            $remaining = Application::where('Student_id', $studentId)->count();

            return response()->json([
                'success'   => true,
                'remaining' => $remaining,
                'redirect'  => route('student.application'), // front-end can use this if remaining == 0
            ]);
        }

        return response()->json(['success' => false], 404);
    }
}
