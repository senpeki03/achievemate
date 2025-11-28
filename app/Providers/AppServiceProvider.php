<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use App\Models\PostRecipient;
use App\Models\ApplicationRecipient;
use App\Models\Application;
use App\Observers\ApplicationObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register container bindings here if needed
    }

    public function boot(): void
    {
        /**
         * ============================
         *  STUDENT UNREAD NOTIFICATIONS
         * ============================
         */
        View::composer([
            'student.studentsidebar',           // e.g. resources/views/student/studentsidebar.blade.php
            'student.partials.studentsidebar',  // depende kung gamit mo ito
        ], function ($view) {
            // Never run heavy DB work during CLI (migrate, tinker, queue:work)
            if (app()->runningInConsole()) {
                $view->with('unreadCount', 0);
                return;
            }

            // Defensive: on the profile page, skip the DB call entirely
            if (request()->routeIs('student.profile')) {
                $view->with('unreadCount', 0);
                return;
            }

            $unread = 0;

            try {
                // Guard required tables/columns
                if (!Schema::hasTable('post_recipient') || !Schema::hasTable('post')) {
                    $view->with('unreadCount', 0);
                    return;
                }

                if (!Schema::hasColumn('post_recipient', 'Student_id') ||
                    !Schema::hasColumn('post_recipient', 'is_read')) {
                    $view->with('unreadCount', 0);
                    return;
                }

                // post end date column (support both cases)
                $endDateCol = Schema::hasColumn('post', 'End_date')
                    ? 'End_date'
                    : (Schema::hasColumn('post', 'end_date') ? 'end_date' : null);

                if (!$endDateCol) {
                    $view->with('unreadCount', 0);
                    return;
                }

                // Get current student id (normalize your session key soon)
                $studentId = session('student_id') ?? session('Student_id');

                if ($studentId) {
                    // Eloquent + relation to ensure End_date filter is on post table
                    $unread = PostRecipient::query()
                        ->where('Student_id', $studentId)
                        ->where('is_read', 0)
                        ->whereHas('post', function ($q) use ($endDateCol) {
                            $q->whereDate($endDateCol, '>=', now()->toDateString());
                        })
                        ->count();
                }
            } catch (\Throwable $e) {
                // Swallow any error to keep the sidebar from crashing the whole page
                $unread = 0;
            }

            $view->with('unreadCount', (int) $unread);
        });

        /**
         * ==============================
         *  PROGRAM CHAIR UNREAD APPLICATIONS
         * ==============================
         */
        View::composer('programchair.*', function ($view) {
            if (app()->runningInConsole()) {
                $view->with('unreadCount', 0);
                return;
            }

            $unread = 0;

            try {
                $login = Auth::user(); 

                if (
                    $login &&
                    mb_strtolower((string) $login->usertype) === 'program chairperson' &&
                    $login->userDesignation
                ) {
                    $userManageId = $login->userDesignation->User_id;

                    if ($userManageId) {
                        $unread = \App\Models\ApplicationRecipient::where('User_id', $userManageId)
                            ->where('is_read', 0)
                            ->count();
                    }
                }

            } catch (\Throwable $e) {
                $unread = 0;
            }

            // ALWAYS pass unreadCount to ALL programchair views
            $view->with('unreadCount', (int) $unread);
        });


        // Keep your model observers and other boot logic
        Application::observe(ApplicationObserver::class);
    }
}
