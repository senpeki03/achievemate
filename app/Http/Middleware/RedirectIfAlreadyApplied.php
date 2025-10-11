<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Application;

class RedirectIfAlreadyApplied
{
    public function handle(Request $request, Closure $next)
    {
        $studentId = session('Student_id');
        if ($studentId && Application::where('Student_id', $studentId)->exists()) {
            return redirect()->route('student.application.status');
        }
        return $next($request);
    }
}
