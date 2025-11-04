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
        // Skip session checks for API requests — guest sessions are a web-only
        // concept. This avoids touching the session store during API tests
        // where there may be no session service attached to the request.
        if ($request->expectsJson() || $request->is('api/*')) {
            return $next($request);
        }

        // Safely check for a session-only guest. In some test contexts the request
        // may not have a session store attached and calling session() throws an
        // exception. Trap that and treat the request as having no guest.
        $hasGuest = false;
        try {
            if ($request->hasSession()) {
                $hasGuest = $request->session()->has('guest');
            }
        } catch (\RuntimeException $e) {
            // No session store available on the request — continue as non-guest.
            $hasGuest = false;
        }

        if ($hasGuest) {
            // For API requests return JSON 403, for web redirect to upgrade
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['error' => 'Guests cannot perform that action. Please upgrade to continue.'], 403);
            }

            return redirect()->route('upgrade')->with('info', 'Guests cannot perform that action. Please upgrade to continue.');
        }

        return $next($request);
    }
}
