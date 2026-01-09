<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CheckUserActive
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check() && !Auth::user()->is_active) {
            Auth::logout();
            return Inertia::location('/login');
        }

        return $next($request);
    }
}

/**
 * IMPORTANT PROJECT RULE:
 * This project uses Inertia.js.
 *
 * NEVER return plain JSON responses
 * for Inertia requests.
 *
 * If user is inactive:
 * - Force logout
 * - Redirect using Inertia::location()
 *
 * Returning JSON will break the SPA and cause:
 * "All Inertia requests must receive a valid Inertia response"
 */