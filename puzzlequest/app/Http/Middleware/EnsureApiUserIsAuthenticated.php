<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

class EnsureApiUserIsAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If Laravel has already resolved an authenticated user, pass through
        if (Auth::check()) {
            return $next($request);
        }

        try {
            // Ensure a token is present first for clearer diagnostics
            $token = JWTAuth::getToken();
            if (!$token) {
                return response()->json(['error' => 'Token not provided'], 401);
            }

            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json(['error' => 'Unauthorized: user not found'], 401);
            }

            // Ensure we operate on the 'api' guard and set the authenticated user so controllers using auth('api')->user() work
            Auth::shouldUse('api');
            Auth::setUser($user);
            // Also ensure the request's user resolver returns the JWT user
            $request->setUserResolver(function () use ($user) { return $user; });

        } catch (TokenExpiredException $e) {
            return response()->json(['error' => 'Token expired'], 401);
        } catch (JWTException $e) {
            return response()->json(['error' => 'Token invalid or missing'], 401);
        }

        return $next($request);
    }
}
