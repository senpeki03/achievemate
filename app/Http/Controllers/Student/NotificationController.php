<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostRecipient;
use App\Models\StudentNotification; // ⬅️ add this
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // Show notifications
    public function showNotifications()
    {
        $studentId   = session('Student_id');
        $currentDate = now();

        if (!$studentId) {
            abort(403, 'Student not logged in.');
        }

        // Most recent active post (for your isApplicationClosed flag)
        $post = Post::where('End_date', '>=', now())
            ->orderBy('Start_date', 'desc')
            ->first();

        $isApplicationClosed = false;
        if ($post) {
            $isApplicationClosed = $currentDate->greaterThan($post->End_date);
        }

        // Unread messages count (for the sidebar badge)
        $unreadCount = PostRecipient::where('Student_id', $studentId)
            ->where('is_read', false)
            ->count();

        // Fetch all posts with ONLY this student's recipient row loaded
        $posts = Post::with(['recipients' => function ($q) use ($studentId) {
                $q->where('Student_id', $studentId);
            }])
            ->orderBy('Start_date', 'desc')
            ->get();

        // ⬇️ Student award/system notifications (for Claim button etc.)
        $notifications = StudentNotification::forStudent($studentId)
        ->orderByDesc('StudentNotification_id')
        ->get();

        return view('student.notifications', [
            'posts'              => $posts,
            'notifications'      => $notifications,   // ⬅️ now available in Blade
            'unreadCount'        => $unreadCount,
            'isApplicationClosed'=> $isApplicationClosed,
        ]);
    }

    // Mark notification as read
    public function markAsRead(Request $request)
    {
        $studentId = session('Student_id');

        $request->validate([
            'id' => 'required|exists:post,Post_id', // keep your table/PK naming
        ]);

        $postRecipient = PostRecipient::where('Post_id', $request->id)
            ->where('Student_id', $studentId)
            ->first();

        if ($postRecipient) {
            $postRecipient->is_read = true;
            $postRecipient->save();
        }

        $unreadCount = PostRecipient::where('Student_id', $studentId)
            ->where('is_read', false)
            ->count();

        return response()->json(['unreadCount' => $unreadCount]);
    }
}
