<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequiresActiveSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! method_exists($user, 'hasActiveSubscription') || ! $user->hasActiveSubscription()) {
            return response()->json([
                'success' => false,
                'message' => "You don't have an active subscription. Please subscribe to a plan to access this feature.",
                'requires_subscription' => true,
            ], 403);
        }

        return $next($request);
    }
}
