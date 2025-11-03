<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class BlockGuestMiddleware
{
    /**
     * Handle an incoming request.
     * If a session-only guest exists, block access and redirect to upgrade.
     */
    public function handle(Request $request, Closure $next)
    {
        // If there's no session (API token requests in tests), allow the request
        if ($request->hasSession() && $request->session()->has('guest')) {
            // For API requests return JSON 403, for web redirect to upgrade
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['error' => 'Guests cannot perform that action. Please upgrade to continue.'], 403);
            }

            return redirect()->route('upgrade')->with('info', 'Guests cannot perform that action. Please upgrade to continue.');
        }

        return $next($request);
    }
}
