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

    /** Academic Year helper, e.g. "2024-2025" (Aug–Jul rule) */
    private function currentAcademicYear(\DateTimeInterface $now = null): string
    {
        $now = $now ?: now();
        $y   = (int) $now->format('Y');
        $m   = (int) $now->format('n');
        return $m >= 8 ? sprintf('%d-%d', $y, $y + 1) : sprintf('%d-%d', $y - 1, $y);
    }

        private function currentSemester(\DateTimeInterface $now = null): string
    {
        $now = $now ?: now();
        $m   = (int) $now->format('n'); // 1..12

        if ($m >= 8 && $m <= 12) {
            return 'First Semester';
        }

        if ($m >= 1 && $m <= 5) {
            return 'Second Semester';
        }

        // June–July
        return 'Midyear Term';
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'           => ['required', 'string', 'max:255'],
            'body'            => ['required', 'string'], // plain text from UI
            'End_date'        => ['required', 'date', 'after_or_equal:today'],
            'image'           => ['nullable','image','mimes:jpeg,png,jpg,gif','max:2048'],
            'student_ids'     => ['nullable','array'],
            'student_ids.*'   => ['integer'],
            // Academic Year (optional; we auto-compute if empty)
            'Academic_year'   => ['nullable','regex:/^\d{4}-\d{4}$/'],
            // Semester (optional; auto-compute if empty)
            'Semester'        => ['nullable','string','max:50'],
        ]);

        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Please log in first.');
        }

        $loginUser      = Auth::user();
        $userDesignation= $loginUser->userDesignation ?? null;
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
        $ay        = $validated['Academic_year'] ?? $this->currentAcademicYear();
        $sem       = $validated['Semester'] ?? $this->currentSemester();


        DB::beginTransaction();
        try {
            /** @var Post $post */
            $post = Post::create([
                'UserDesignation_id' => $userDesignation->UserDesignation_id,
                'Title'              => $validated['title'],
                'Announcement'       => $cleanBody,
                'Academic_year'      => $ay,
                'Semester'           => $sem,      // ✅ save Semester
                'Start_date'         => now(),
                'End_date'           => $validated['End_date'],
                'image'              => $imagePath,
            ]);

            $postId = (int) $post->Post_id;

            // Recipients: explicit list > everyone
            $explicitIds = collect($request->input('student_ids', []))
                ->filter(fn ($v) => (string)$v !== '')
                ->map(fn ($v) => (int) $v)
                ->unique()
                ->values();

            $insertCount = 0;

            if ($explicitIds->isNotEmpty()) {
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
                // All students (streamed)
                StudentManage::query()
                    ->orderBy('Student_id')
                    ->chunk(2000, function ($students) use ($postId, &$insertCount) {
                        $rows = $students->map(fn ($s) => [
                            'Post_id'    => $postId,
                            'Student_id' => (int) $s->Student_id,
                            'is_read'    => 0,
                        ])->all();
                        if ($rows) {
                            DB::table('post_recipient')->insert($rows);
                            $insertCount += count($rows);
                        }
                    });
            }

            DB::commit();

            if ($insertCount === 0) {
                return back()->with('success', 'Post created, but no recipients matched your filters.');
            }

            return back()->with('success', "Post created for Academic Year {$ay} and sent to {$insertCount} recipient(s).");
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return back()->with('error', 'Post failed: '.$e->getMessage());
        }
    }
}
