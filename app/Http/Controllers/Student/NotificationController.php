<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostRecipient;
use App\Models\StudentNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    /** One place to read the currently logged-in student id */
    private function sid(): int
    {
        // You’re storing it in the session:
        $sid = (int) session('Student_id');
        abort_if(!$sid, 403, 'Student not logged in.');
        return $sid;
    }

    /** Page: Messages + Awards (claimables) */
    public function showNotifications()
    {
        $studentId   = $this->sid();
        $now         = now();

        // latest active post (for your "application closed" banner/flag)
        $post = Post::where('End_date', '>=', $now)
            ->orderBy('Start_date', 'desc')
            ->first();

        $isApplicationClosed = $post ? $now->greaterThan($post->End_date) : false;

        // unread messages badge
        $unreadCount = PostRecipient::where('Student_id', $studentId)
            ->where('is_read', false)
            ->count();

        // posts, but only this student's recipient row is eager-loaded
        $posts = Post::with(['recipients' => function ($q) use ($studentId) {
                $q->where('Student_id', $studentId);
            }])
            ->orderBy('Start_date', 'desc')
            ->get();

        // awards/claimables
        $notifications = StudentNotification::forStudent($studentId)
            ->orderByDesc('StudentNotification_id')
            ->get();

        return view('student.notifications', [
            'posts'               => $posts,
            'notifications'       => $notifications,
            'unreadCount'         => $unreadCount,
            'isApplicationClosed' => $isApplicationClosed,
        ]);
    }

    /** AJAX: mark a message (Post) as read */
    public function markAsRead(Request $request)
    {
        $studentId = $this->sid();

        // If your table is literally named "post" with PK "Post_id", keep it:
        $request->validate([
            'id' => 'required|integer',
        ]);

        $rec = PostRecipient::where('Post_id', $request->integer('id'))
            ->where('Student_id', $studentId)
            ->first();

        if ($rec && !$rec->is_read) {
            $rec->is_read = true;
            $rec->save();
        }

        $unreadCount = PostRecipient::where('Student_id', $studentId)
            ->where('is_read', false)
            ->count();

        return response()->json(['unreadCount' => $unreadCount]);
    }

    /** GET /student/award/claim/{token} — move into portfolios then mark claimed */
    public function claim(string $token)
    {
        $studentId = $this->sid();

        $n = StudentNotification::forStudent($studentId)
            ->where('claim_token', $token)
            ->firstOrFail();

        // If already claimed or not claimable, be idempotent.
        if (!$n->claim()) {
            return redirect()
                ->route('student.notifications')
                ->with('status', 'Already claimed.');
        }

        // Insert into portfolios table (adjust columns if yours differ)
        DB::table('portfolios')->insert([
            'Student_id'       => $studentId,
            'type'             => 'deans_lister',
            'title'            => $n->title ?? 'Dean’s Lister',
            'description'      => $n->message ?? 'Dean’s Lister recognition',
            'badge_path'       => data_get($n->data, 'badge_path'),
            'certificate_path' => data_get($n->data, 'certificate_path'),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        // optional: also mark the notification as read
        $n->markRead();

        return redirect()
            ->route('student.notifications')
            ->with('status', 'Certificate added to your portfolio.');
    }
}
