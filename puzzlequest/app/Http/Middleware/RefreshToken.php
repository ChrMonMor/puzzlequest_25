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
            // Try authenticating using the current token. If no token present, parseToken() will throw
            // an exception we don't want to convert into a 500 — just continue the request as unauthenticated.
            JWTAuth::parseToken()->authenticate();
            return $next($request);
        } catch (TokenExpiredException $e) {
            // Token is present but expired — attempt a refresh and attach the new token to the response.
            try {
                $current = JWTAuth::getToken();
                if (!$current) {
                    return $next($request);
                }

                // Refresh the expired token
                $newToken = JWTAuth::refresh($current);

                // Replace the token in the manager so downstream auth() calls will use the refreshed token
                JWTAuth::setToken($newToken)->toUser();

                $response = $next($request);

                // Send new token back in the Authorization header for clients to pick up
                if (is_object($response) && method_exists($response, 'headers')) {
                    $response->headers->set('Authorization', 'Bearer ' . $newToken);
                    // Ensure browsers can read the Authorization header in CORS responses
                    $response->headers->set('Access-Control-Expose-Headers', 'Authorization');
                }

                return $response;
            } catch (Exception $ex) {
                // Refresh failed — tell client to log in again
                return response()->json([
                    'error' => 'Token expired, please login again'
                ], 401);
            }
        } catch (Exception $e) {
            // No token present or other non-expiration issue: allow the request to continue unauthenticated.
            return $next($request);
        }
    }
}
