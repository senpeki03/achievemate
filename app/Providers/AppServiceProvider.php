<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\DB;
use App\Models\Application;
use App\Observers\ApplicationObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ...
    }

    public function boot(): void
    {
        // 1) Share unread notifications count to all views
        View::composer('*', function ($view) {
            $unread = 0;

            try {
                // optional: skip during artisan commands or before tables exist
                if (app()->runningInConsole()) {
                    $view->with('unreadCount', 0);
                    return;
                }

                $studentId = session('Student_id');

                if ($studentId) {
                    $unread = DB::table('post_recipient as pr')
                        ->join('post as p', 'p.Post_id', '=', 'pr.Post_id')
                        ->where('pr.Student_id', $studentId)
                        ->where('pr.is_read', 0)
                        ->whereDate('p.End_date', '>=', now()->toDateString())
                        ->count();
                }
            } catch (\Throwable $e) {
                $unread = 0; // keep UI safe even if something fails
            }

            $view->with('unreadCount', (int) $unread);
        });

        // 2) Register model observer
        Application::observe(ApplicationObserver::class);
    }
}
