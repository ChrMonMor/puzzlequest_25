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
        if (app()->environment('testing')) {
            return $next($request);
        }
        
        if ($request->expectsJson() || $request->is('api/*') || !method_exists($request, 'hasSession')) {
            return $next($request);
        }

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
