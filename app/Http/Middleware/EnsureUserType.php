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
            return redirect()->route('login');
        }

        // Normalize both sides (case-insensitive)
        $actual   = trim(mb_strtolower((string)($user->usertype ?? '')));
        $expected = trim(mb_strtolower($expected));

        if ($actual !== $expected) {
            abort(403, 'Unauthorized.');
        }

        return $next($request);
    }
}
