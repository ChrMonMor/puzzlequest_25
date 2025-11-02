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
        if ($request->session()->has('guest')) {
            // Redirect guests to upgrade page with a notice
            return redirect()->route('upgrade')->with('info', 'Guests cannot perform that action. Please upgrade to continue.');
        }

        return $next($request);
    }
}
