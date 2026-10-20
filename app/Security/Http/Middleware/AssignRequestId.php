<?php

declare(strict_types=1);

namespace App\Security\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every request a correlation id that appears in each security event and in
 * the X-Request-Id response header, so a user-reported error can be found in the SIEM.
 */
final class AssignRequestId
{
    public const ATTRIBUTE = 'security.request_id';
    public const HEADER = 'X-Request-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $incoming = (string) $request->headers->get(self::HEADER, '');

        // Accept an upstream id (load balancer / proxy) only if it is safe to log verbatim.
        $id = preg_match('/^[A-Za-z0-9._-]{8,64}$/', $incoming) === 1 ? $incoming : (string) Str::uuid();

        $request->attributes->set(self::ATTRIBUTE, $id);

        $response = $next($request);
        $response->headers->set(self::HEADER, $id);

        return $response;
    }
}
