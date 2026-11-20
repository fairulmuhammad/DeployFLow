<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every request a stable correlation id.
 *
 * The id is echoed on the response and is available to error envelopes via
 * `X-Request-Id`, per docs/API.md §1.
 */
final class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->headers->get('X-Request-Id');

        if ($requestId === null || preg_match('/^[A-Za-z0-9._-]{1,100}$/', $requestId) !== 1) {
            $requestId = (string) Str::uuid7();
        }

        $request->headers->set('X-Request-Id', $requestId);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
