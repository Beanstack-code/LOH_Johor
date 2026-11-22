<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Preserve the raw request body in a request attribute so that
 * webhook controllers can use it for HMAC signature verification.
 *
 * Laravel's JSON middleware reads php://input and makes it available
 * through $request->getContent(); however, explicitly capturing it here
 * ensures the raw bytes are available before any stream buffering happens.
 */
class CaptureRawBody
{
    public function handle(Request $request, Closure $next): Response
    {
        // getContent(true) reads the stream and caches the result; subsequent
        // calls to getContent() return the same cached string.
        $request->getContent();

        return $next($request);
    }
}
