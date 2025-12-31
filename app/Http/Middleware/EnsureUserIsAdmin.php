<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()->role !== 'admin') {
            abort(403);
        }

        return $next($request);
    }
}

/**
 * NOTE:
 * This middleware may be executed for Inertia requests.
 * Do NOT return JSON responses here.
 * Use redirect or abort if access is denied.
 */