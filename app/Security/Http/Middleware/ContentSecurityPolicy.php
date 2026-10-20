<?php

declare(strict_types=1);

namespace App\Security\Http\Middleware;

use App\Security\Config\CspSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a CSP whose violations the browser reports back to CspReportController.
 * Start in report-only mode, review the reports, then switch to enforcing.
 */
final class ContentSecurityPolicy
{
    public function __construct(private readonly CspSettings $settings)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!$this->settings->enabled || !$this->isHtml($response)) {
            return $response;
        }

        if (!$response->headers->has($this->settings->headerName())) {
            $response->headers->set($this->settings->headerName(), $this->settings->headerValue());
        }

        return $response;
    }

    private function isHtml(Response $response): bool
    {
        $type = (string) $response->headers->get('Content-Type', '');

        return $type === '' || str_contains(strtolower($type), 'text/html');
    }
}
