<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response;

class ResolveApiGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $payload = JWTAuth::parseToken()->getPayload();
            $role = $payload->get('role');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token is invalid or expired.',
            ], 401);
        }

        $guard = match ($role) {
            'member' => 'member_api',
            'merchant' => 'merchant_api',
            default => null,
        };

        if (! $guard) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Invalid account type.',
            ], 401);
        }

        auth()->shouldUse($guard);

        if (! auth($guard)->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        return $next($request);
    }
}
