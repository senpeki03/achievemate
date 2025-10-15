<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserType
{
    public function handle(Request $request, Closure $next, string $expected)
    {
        $user = Auth::user();

        // Not logged in?
        if (!$user) {
            // redirect to login or abort 403; choose one
            return redirect()->route('login'); // or: abort(403);
        }

        // Normalize both sides to avoid case / whitespace issues
        $actual = trim(mb_strtolower((string)($user->usertype ?? '')));
        $expected = trim(mb_strtolower($expected));

        if ($actual !== $expected) {
            abort(403, 'Unauthorized.');
        }

        return $next($request);
    }
}
