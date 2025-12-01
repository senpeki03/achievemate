<?php

namespace App\Http\Controllers\Programchair;

use App\Http\Controllers\Controller;
use App\Mail\StudentEventInviteMail;
use App\Models\EventType;
use App\Models\UserManage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\EventStudentAssignment;
use App\Models\StudentManage;
use App\Models\Event;
use App\Models\UserDesignation;
use Illuminate\Support\Facades\Mail;

class EventController extends Controller
{
    /**
     * Display events owned by the current Program Chair.
     */
    public function index()
    {
        $login = auth()->user();
        if (!$login) {
            abort(403, 'Unauthorized');
        }

        $designation = UserDesignation::with(['designation', 'college', 'program', 'major'])
            ->where('Login_id', $login->Login_id)
            ->first();

        if (!$designation) {
            return redirect()
                ->back()
                ->with('error', 'No designation found for this user.');
        }

        $events = Event::where('UserDesignation_id', $designation->UserDesignation_id)
            ->withCount(['assignments as assigned_count'])
            ->with([
                'assignments.student',
                'eventType',      // 🔹 include type
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        $students = StudentManage::orderBy('Last_name')
            ->orderBy('First_name')
            ->get();

        // 🔹 Fetch event types (for dropdown + modal)
        $eventTypes = EventType::orderBy('EventType_id')->get();

        return view('programchair.events', compact('events', 'students', 'eventTypes'));
    }

    /**
     * Store a newly created event.
     */
    public function store(Request $request)
    {
        $login = auth()->user();
        if (!$login) {
            abort(403, 'Unauthorized');
        }

        $designation = UserDesignation::where('Login_id', $login->Login_id)->first();

        if (!$designation) {
            return redirect()->back()->with('error', 'No designation found for this user.');
        }

        $validated = $request->validate([
            'EventType_id'       => 'required|integer|exists:event_types,EventType_id',
            'title'              => 'required|string|max:255',
            'description'        => 'nullable|string',
            'start_at'           => 'required|date',
            'end_at'             => 'required|date|after_or_equal:start_at',
            'number_of_students' => 'nullable|integer|min:0',
        ]);

        $event = new Event();
        $event->UserDesignation_id  = $designation->UserDesignation_id;
        $event->EventType_id        = $validated['EventType_id'];
        $event->title               = $validated['title'];
        $event->description         = $validated['description'] ?? null;
        $event->start_at            = $validated['start_at'];
        $event->end_at              = $validated['end_at'];
        $event->number_of_students  = $validated['number_of_students'] ?? 0;
        $event->status              = 'Pending';
        $event->save();

        return redirect()
            ->route('programchair.events')
            ->with('success', 'Event created successfully.');
    }

    /**
     * Update an existing event.
     */
    public function update(Request $request, $id)
    {
        $login = auth()->user();
        if (!$login) {
            abort(403, 'Unauthorized');
        }

        $designation = UserDesignation::where('Login_id', $login->Login_id)->first();

        if (!$designation) {
            return redirect()->back()->with('error', 'No designation found for this user.');
        }

        $validated = $request->validate([
            'EventType_id'       => 'required|integer|exists:event_types,EventType_id',
            'title'              => 'required|string|max:255',
            'description'        => 'nullable|string',
            'start_at'           => 'required|date',
            'end_at'             => 'required|date|after_or_equal:start_at',
            'number_of_students' => 'nullable|integer|min:0',
        ]);

        $event = Event::where('UserDesignation_id', $designation->UserDesignation_id)
            ->where('Event_id', $id)
            ->firstOrFail();

        $event->EventType_id       = $validated['EventType_id'];
        $event->title              = $validated['title'];
        $event->description        = $validated['description'] ?? null;
        $event->start_at           = $validated['start_at'];
        $event->end_at             = $validated['end_at'];
        $event->number_of_students = $validated['number_of_students'] ?? 0;
        $event->save();

        return back()->with('success', 'Event updated successfully.');
    }

    /**
     * Delete an event (only if no invites, or adjust logic if you want).
     */
    public function destroy($id)
    {
        $login = auth()->user();
        if (!$login) {
            abort(403, 'Unauthorized');
        }

        $designation = UserDesignation::where('Login_id', $login->Login_id)->first();

        if (!$designation) {
            return redirect()->back()->with('error', 'No designation found for this user.');
        }

        $event = Event::where('UserDesignation_id', $designation->UserDesignation_id)
            ->where('Event_id', $id)
            ->withCount('assignments')
            ->firstOrFail();

        if ($event->assignments_count > 0) {
            return back()->with('error', 'Cannot delete this event because there are already invited students.');
        }

        $event->delete();

        return back()->with('success', 'Event deleted successfully.');
    }

    /**
     * Invite a student to an event.
     */
    public function inviteStudent(Request $request)
    {
        Log::info('InviteStudent() — Incoming request', [
            'event_id'     => $request->input('event_id'),
            'student_id'   => $request->input('student_id'),
            'full_request' => $request->all()
        ]);

        try {
            $request->validate([
                'event_id'   => 'required|integer|exists:events,Event_id',
                'student_id' => 'required|integer|exists:student_manage,Student_id',
            ]);

            $eventId   = $request->input('event_id');
            $studentId = $request->input('student_id');

            // 🔹 GET INVITER NAME VIA USER DESIGNATION
            $login = auth()->user();

            $designation = UserDesignation::where('Login_id', $login->Login_id)->first();

            $inviterName = 'Program Chair';

            if ($designation) {
                $inviter = UserManage::find($designation->User_id);

                if ($inviter) {
                    $middle = $inviter->Middle_name ? $inviter->Middle_name . ' ' : '';
                    $inviterName = trim($inviter->First_name . ' ' . $middle . $inviter->Last_name);
                }
            }

            // Load event with eventType
            $event = Event::with(['assignments', 'eventType'])->findOrFail($eventId);

            // Capacity check
            if ($event->number_of_students > 0) {
                $acceptedCount = $event->assignments()
                    ->where('status', 'Accepted')
                    ->count();

                if ($acceptedCount >= $event->number_of_students) {
                    return back()->with('error', 'This event already has complete participants.');
                }
            }

            // Check existing invite
            $existing = EventStudentAssignment::where('Event_id', $eventId)
                ->where('Student_id', $studentId)
                ->first();

            if ($existing) {
                return back()->with('error', 'This student has already been invited to this event.');
            }

            // Create invite
            $invite = EventStudentAssignment::create([
                'Event_id'   => $eventId,
                'Student_id' => $studentId,
                'status'     => 'Pending',
            ]);

            // Student record
            $student = StudentManage::find($studentId);

            // Send email
            if ($student && $student->Email) {
                Mail::to($student->Email)->send(
                    new StudentEventInviteMail($event, $student, $inviterName)
                );
            }

            Log::info('InviteStudent() — Email sent to: ' . ($student->Email ?? 'NO EMAIL'));

            return back()->with('success', 'Student invited successfully and email notification sent.');

        } catch (\Throwable $e) {
            Log::error('InviteStudent() — ERROR OCCURRED', [
                'exception_message' => $e->getMessage(),
                'line'              => $e->getLine(),
                'file'              => $e->getFile(),
            ]);

            return back()->with('error', 'Invite failed: ' . $e->getMessage());
        }
    }

    /**
     * Cancel an invite (called from modal).
     * URI: programchair/events/{event}/invites/{invite}
     */
    public function cancelInvite(Event $event, EventStudentAssignment $invite)
    {
        if ($invite->Event_id !== $event->Event_id) {
            abort(404);
        }

        $invite->status = 'Cancelled';
        $invite->save();

        return back()->with('success', 'Invitation cancelled successfully.');
    }

    /**
     * Store new event type.
     */
    public function storeEventType(Request $request)
    {
        $request->validate([
            'type_name' => 'required|string|max:255|unique:event_types,type_name',
        ]);

        EventType::create([
            'type_name' => $request->type_name,
        ]);

        return back()->with('success', 'Event type added successfully.');
    }

    /**
     * Update event type.
     */
    public function updateEventType(Request $request, $id)
    {
        $request->validate([
            'type_name' => 'required|string|max:255|unique:event_types,type_name,' . $id . ',EventType_id',
        ]);

        $type = EventType::findOrFail($id);
        $type->type_name = $request->type_name;
        $type->save();

        return back()->with('success', 'Event type updated successfully.');
    }

    /**
     * Delete event type (if not used).
     */
    public function destroyEventType($id)
    {
        $type = EventType::findOrFail($id);

        if (method_exists($type, 'events') && $type->events()->exists()) {
            return back()->with('error', 'Cannot delete this event type because there are events using it.');
        }

        $type->delete();

        return back()->with('success', 'Event type deleted successfully.');
    }
}
