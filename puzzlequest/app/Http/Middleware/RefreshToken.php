<?php
namespace App\Http\Middleware;

use Closure;
use Exception;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

class RefreshToken
{
    public function handle($request, Closure $next)
    {
        try {
            // Try authenticating using the current token
            JWTAuth::parseToken()->authenticate();
        } catch (TokenExpiredException $e) {
            try {
                // Refresh the expired token
                $newToken = JWTAuth::refresh(JWTAuth::getToken());
                // Set the new token so the next request uses it
                JWTAuth::setToken($newToken)->toUser();

                $response = $next($request);
                // Send new token back in the Authorization header
                $response->headers->set('Authorization', 'Bearer ' . $newToken);

                return $response;
            } catch (Exception $ex) {
                return response()->json([
                    'error' => 'Token expired, please login again'
                ], 401);
            }
        }

        return $next($request);
    }
}
