<?php

namespace App\Http\Middleware;

use Closure;
use Throwable;

class ForceJsonResponse
{
    public function handle($request, Closure $next)
    {
    // Debug: log every request to see if middleware runs
    \Log::info('ForceJsonResponse middleware triggered for: ' . $request->path());
        try {
            return $next($request);
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

