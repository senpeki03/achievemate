<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\EventStudentAssignment;
use App\Models\StudentManage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentEventController extends Controller
{
    public function index()
    {
        $login = Auth::user();
        if (!$login) {
            abort(403, 'Unauthorized');
        }

        // Get the logged in student
        $student = StudentManage::where('Login_id', $login->Login_id)->firstOrFail();

        // Load invites + event + eventType
        $invites = EventStudentAssignment::with(['event.eventType'])
            ->where('Student_id', $student->Student_id)
            ->orderByDesc('created_at')
            ->get();

        return view('student.event-invite', compact('invites', 'student'));
    }

    /**
     * Accept / Decline invite.
     * Route: POST /event-invites/{invite}/status
     */
    public function updateStatus(Request $request, $invite)
    {
        $login = Auth::user();
        if (!$login) {
            abort(403, 'Unauthorized');
        }

        $student = StudentManage::where('Login_id', $login->Login_id)->firstOrFail();

        $data = $request->validate([
            'status' => 'required|in:Accepted,Declined',
        ]);

        // Only allow the owner of this invite to update it
        $assignment = EventStudentAssignment::where('Assignment_id', $invite)
            ->where('Student_id', $student->Student_id)
            ->firstOrFail();

        // Don’t allow updates if already cancelled
        if ($assignment->status === 'Cancelled') {
            return back()->with('error', 'This invitation has been cancelled and cannot be updated.');
        }

        // Update status
        $assignment->status = $data['status'];
        $assignment->save();

        // 🔹 If just accepted, check if event is now full → mark event as Complete
        if ($data['status'] === 'Accepted') {
            $event = $assignment->event;   // make sure EventStudentAssignment has event() relation

            if ($event && $event->number_of_students > 0) {
                $acceptedCount = $event->assignments()
                    ->where('status', 'Accepted')
                    ->count();

                if ($acceptedCount >= $event->number_of_students) {
                    $event->status = 'Complete';
                    $event->save();
                }
            }
        }

        return back()->with('success', 'Invitation ' . $data['status'] . '.');
    }
}
