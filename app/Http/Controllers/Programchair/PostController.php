<?php

namespace App\Http\Controllers\Programchair;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Post;
use App\Models\StudentManage;

class PostController extends Controller
{
    public function index()
    {
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Please log in first.');
        }

        return view('programchair.post');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'         => ['required', 'string', 'max:255'],
            'body'          => ['required', 'string'], // plain text from UI
            'End_date'      => ['required', 'date', 'after_or_equal:today'],
            'image'         => ['nullable','image','mimes:jpeg,png,jpg,gif','max:2048'],
            'student_ids'   => ['nullable','array'],
            'student_ids.*' => ['integer'],
        ]);

        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Please log in first.');
        }

        $loginUser = Auth::user();
        $userDesignation = $loginUser->userDesignation ?? null;
        if (!$userDesignation) {
            return back()->with('error', 'User designation not found.');
        }

        // Optional cover image
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('uploads/posts', 'public');
        }

        // Force plain text on server (defense-in-depth)
        $cleanBody = trim(strip_tags($validated['body']));

        DB::beginTransaction();
        try {
            // 1) Insert into `post`
            /** @var Post $post */
            $post = Post::create([
                'UserDesignation_id' => $userDesignation->UserDesignation_id,
                'Title'              => $validated['title'],
                'Announcement'       => $cleanBody,
                'Start_date'         => now(),
                'End_date'           => $validated['End_date'],
                'image'              => $imagePath,
            ]);

            $postId = (int) $post->Post_id;

            // 2) Resolve recipients (explicit list > default all students)
            $explicitIds = collect($request->input('student_ids', []))
                ->filter(fn ($v) => (string)$v !== '')
                ->map(fn ($v) => (int) $v)
                ->unique()
                ->values();

            // Build an iterator of Student IDs
            $insertCount = 0;

            if ($explicitIds->isNotEmpty()) {
                // Insert explicit list in 1k chunks
                foreach ($explicitIds->chunk(1000) as $chunk) {
                    $rows = $chunk->map(fn ($sid) => [
                        'Post_id'    => $postId,
                        'Student_id' => (int) $sid,
                        'is_read'    => 0,
                    ])->all();

                    DB::table('post_recipient')->insert($rows);
                    $insertCount += count($rows);
                }
            } else {
                // Default: all students, streamed to avoid memory blowups
                StudentManage::query()
                    ->orderBy('Student_id')
                    ->chunk(2000, function ($students) use ($postId, &$insertCount) {
                        $rows = $students->map(fn ($s) => [
                            'Post_id'    => $postId,
                            'Student_id' => (int) $s->Student_id,
                            'is_read'    => 0,
                        ])->all();

                        if (!empty($rows)) {
                            DB::table('post_recipient')->insert($rows);
                            $insertCount += count($rows);
                        }
                    });
            }

            DB::commit();

            if ($insertCount === 0) {
                // Post exists, but no matching recipients
                return back()->with('success', 'Post created, but no recipients matched your filters.');
            }

            return back()->with('success', "Post created and sent to {$insertCount} recipient(s).");
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return back()->with('error', 'Post failed: '.$e->getMessage());
        }
    }
}
