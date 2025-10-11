<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Post;
use App\Models\PostRecipient;

class DashboardStudentController extends Controller
{
    public function index(Request $request)
    {
        // 1) Logged-in student
        $loginId = session('Login_id');

        if (!$loginId) {
            $unreadCount = 0;
            $posts = collect();
            return view('student.dashboard', compact('unreadCount', 'posts'));
        }

        // 2) Unread count
        $unreadCount = PostRecipient::where('Student_id', $loginId)
            ->where('is_read', false)
            ->count();

        // 3) Does this student have any targeted posts?
        $hasTargeted = PostRecipient::where('Student_id', $loginId)->exists();

        // 4) Base query for posts
        $query = Post::query()
            ->select(['Post_id','UserDesignation_id','Title','Announcement','Start_date','End_date','image'])
            ->with([
                'recipients' => function ($q) use ($loginId) {
                    $q->where('Student_id', $loginId)
                      ->select('Post_id', 'Student_id', 'is_read');
                }
            ])
            ->orderByDesc('Start_date')
            ->orderByDesc('End_date');

        // If there are targeted posts, show only those; else show all posts (fallback)
        if ($hasTargeted) {
            $query->whereHas('recipients', function ($q) use ($loginId) {
                $q->where('Student_id', $loginId);
            });
        }

        $posts = $query->paginate(6)->withQueryString();

        return view('student.dashboard', compact('unreadCount', 'posts'));
    }
}
