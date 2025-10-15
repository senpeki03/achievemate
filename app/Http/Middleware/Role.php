<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Role
{
    public function handle(Request $request, Closure $next, string $role)
    {
        // adjust this to how you store roles
        if (auth()->check() && strcasecmp(auth()->user()->role ?? '', $role) === 0) {
            return $next($request);
        }

        abort(403, 'Forbidden');
    }
}
