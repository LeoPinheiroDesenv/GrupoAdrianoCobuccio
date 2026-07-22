<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Validates the JWT token from the Authorization header.
     * Sets the authenticated user on success.
     * Returns 401 JSON response on any token failure.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                Log::warning('auth.unauthorized', [
                    'ip' => $request->ip(),
                    'path' => $request->path(),
                    'reason' => 'invalid',
                ]);

                return response()->json(['error' => 'Não autorizado'], 401);
            }
        } catch (TokenExpiredException $e) {
            Log::warning('auth.unauthorized', [
                'ip' => $request->ip(),
                'path' => $request->path(),
                'reason' => 'expired',
            ]);

            return response()->json(['error' => 'Não autorizado'], 401);
        } catch (TokenInvalidException $e) {
            Log::warning('auth.unauthorized', [
                'ip' => $request->ip(),
                'path' => $request->path(),
                'reason' => 'invalid',
            ]);

            return response()->json(['error' => 'Não autorizado'], 401);
        } catch (JWTException $e) {
            Log::warning('auth.unauthorized', [
                'ip' => $request->ip(),
                'path' => $request->path(),
                'reason' => 'missing',
            ]);

            return response()->json(['error' => 'Não autorizado'], 401);
        }

        return $next($request);
    }
}
