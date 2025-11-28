<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        // You can add endpoints you want to skip CSRF on:
        'student/qr/resolve',
        'student/qr/resolve*',
        'cog/validate-from-text',
        'student/cog/validate-from-text',
    ];
}
