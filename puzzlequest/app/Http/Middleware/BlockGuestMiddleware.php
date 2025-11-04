<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BlockGuestMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Skip in testing environment
        if (app()->environment('testing')) {
            return $next($request);
        }

        // Skip for API requests (you can adjust this if needed)
        if ($request->expectsJson() || $request->is('api/*')) {
            return $next($request);
        }

        // Get guest token from Bearer header or query/body
        $token = $request->bearerToken() ?? $request->query('guest_token') ?? $request->input('guest_token');

        if ($token && Cache::has("guest:{$token}")) {
            // Guest exists in cache → block
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'Guests cannot perform that action. Please upgrade to continue.'
                ], 403);
            }

            return redirect()->route('upgrade')
                ->with('info', 'Guests cannot perform that action. Please upgrade to continue.');
        }

        return $next($request);
    }
}
