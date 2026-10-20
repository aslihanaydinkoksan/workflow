<?php

declare(strict_types=1);

namespace App\Security\Http\Middleware;

use App\Security\Config\InspectionSettings;
use App\Security\Contracts\SecurityEventLogger;
use App\Security\Detection\InspectionFinding;
use App\Security\Detection\PayloadInspector;
use App\Security\Detection\ScannerUserAgentDetector;
use App\Security\Enums\SecurityEventType;
use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global middleware. Flags SQLi/XSS signatures and known scanner User-Agents.
 * Default mode is "monitor" (log only); "block" returns 403 on a signature hit.
 */
final class InspectRequestPayload
{
    private const MAX_FIELDS_PER_EVENT = 10;
    private const SCANNER_UA_DEDUP_SECONDS = 600;

    public function __construct(
        private readonly PayloadInspector $inspector,
        private readonly ScannerUserAgentDetector $scannerDetector,
        private readonly InspectionSettings $settings,
        private readonly SecurityEventLogger $logger,
        private readonly RateLimiter $limiter,
        private readonly ResponseFactory $responses,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->settings->enabled || $this->isExcludedPath($request)) {
            return $next($request);
        }

        $this->checkUserAgent($request);

        $findings = $this->inspector->inspect($this->collectInput($request));

        if ($findings === []) {
            return $next($request);
        }

        $this->recordFindings($request, $findings);

        if ($this->settings->blocks()) {
            $request->attributes->set(ThrottleLoginAttempts::HANDLED_ATTRIBUTE, true);

            return $request->expectsJson()
                ? $this->responses->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN)
                : $this->responses->make('Forbidden.', Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    /**
     * @return array<string, mixed>
     */
    private function collectInput(Request $request): array
    {
        $body = $request->isJson() ? $request->json()->all() : $request->request->all();

        return [
            'path' => rawurldecode($request->getPathInfo()),
            'query' => $request->query->all(),
            'body' => is_array($body) ? $body : [],
            'header' => array_filter([
                'referer' => $request->headers->get('referer'),
                'user-agent' => $request->headers->get('user-agent'),
                'x-forwarded-host' => $request->headers->get('x-forwarded-host'),
            ], static fn (?string $v): bool => $v !== null && $v !== ''),
        ];
    }

    /**
     * @param list<InspectionFinding> $findings
     */
    private function recordFindings(Request $request, array $findings): void
    {
        $byCategory = [];

        foreach ($findings as $finding) {
            $byCategory[$finding->result->category][] = $finding;
        }

        foreach ($byCategory as $category => $categoryFindings) {
            $type = match ($category) {
                'sqli' => SecurityEventType::SqlInjectionPattern,
                'xss' => SecurityEventType::XssPattern,
                default => null,
            };

            if ($type === null) {
                continue;
            }

            $this->logger->record($type, [
                'action' => $this->settings->blocks() ? 'blocked' : 'monitored',
                'finding_count' => count($categoryFindings),
                'rules' => array_values(array_unique(array_map(
                    static fn (InspectionFinding $f): string => $f->result->ruleId,
                    $categoryFindings,
                ))),
                'findings' => array_map(static fn (InspectionFinding $f): array => [
                    'location' => $f->location,
                    'field' => $f->field,
                    'rule' => $f->result->ruleId,
                    'matched' => $f->result->matched,
                ], array_slice($categoryFindings, 0, self::MAX_FIELDS_PER_EVENT)),
            ], request: $request);
        }
    }

    private function checkUserAgent(Request $request): void
    {
        $tool = $this->scannerDetector->detect($request->userAgent());

        if ($tool === null) {
            return;
        }

        // One event per IP+tool per window; the scan itself may be thousands of requests.
        $key = 'security:scanner-ua:' . $request->ip() . ':' . $tool;

        if ($this->limiter->hit($key, self::SCANNER_UA_DEDUP_SECONDS) === 1) {
            $this->logger->record(SecurityEventType::ScannerUserAgent, [
                'tool' => $tool,
                'dedup_window_seconds' => self::SCANNER_UA_DEDUP_SECONDS,
            ], request: $request);
        }
    }

    private function isExcludedPath(Request $request): bool
    {
        return $this->settings->excludedPaths !== [] && $request->is(...$this->settings->excludedPaths);
    }
}
