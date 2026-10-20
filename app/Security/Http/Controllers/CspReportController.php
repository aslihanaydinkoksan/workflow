<?php

declare(strict_types=1);

namespace App\Security\Http\Controllers;

use App\Security\Contracts\SecurityEventLogger;
use App\Security\Enums\SecurityEventType;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Receives browser CSP violation reports (legacy report-uri and Reporting API formats).
 * A violation on a page that should never load third-party script is a strong XSS signal.
 */
final class CspReportController
{
    private const MAX_BODY_BYTES = 65536;
    private const MAX_REPORTS = 5;
    private const NOISE_SCHEMES = ['chrome-extension', 'moz-extension', 'safari-extension', 'safari-web-extension', 'ms-browser-extension'];

    public function __construct(private readonly SecurityEventLogger $logger)
    {
    }

    public function __invoke(Request $request): Response
    {
        $raw = $request->getContent();

        if ($raw === '' || strlen($raw) > self::MAX_BODY_BYTES) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        foreach (array_slice($this->extractReports($decoded), 0, self::MAX_REPORTS) as $report) {
            $normalized = $this->normalize($report);

            if ($this->isBrowserExtensionNoise($normalized)) {
                continue;
            }

            $this->logger->record(SecurityEventType::CspViolation, $normalized, request: $request);
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @param array<mixed> $decoded
     * @return list<array<string, mixed>>
     */
    private function extractReports(array $decoded): array
    {
        // Legacy report-uri: {"csp-report": {...}}
        if (isset($decoded['csp-report']) && is_array($decoded['csp-report'])) {
            return [$decoded['csp-report']];
        }

        // Reporting API (report-to): [{"type": "csp-violation", "body": {...}}, ...]
        $reports = [];

        foreach ($decoded as $entry) {
            if (is_array($entry) && ($entry['type'] ?? null) === 'csp-violation' && is_array($entry['body'] ?? null)) {
                $reports[] = $entry['body'];
            }
        }

        return $reports;
    }

    /**
     * @param array<string, mixed> $r
     * @return array<string, mixed>
     */
    private function normalize(array $r): array
    {
        $pick = static function (string ...$keys) use ($r): mixed {
            foreach ($keys as $key) {
                if (isset($r[$key]) && is_scalar($r[$key])) {
                    return $r[$key];
                }
            }

            return null;
        };

        return [
            'document_uri' => $pick('document-uri', 'documentURL'),
            'blocked_uri' => $pick('blocked-uri', 'blockedURL'),
            'violated_directive' => $pick('effective-directive', 'effectiveDirective', 'violated-directive'),
            'source_file' => $pick('source-file', 'sourceFile'),
            'line_number' => $pick('line-number', 'lineNumber'),
            'script_sample' => $pick('script-sample', 'sample'),
            'disposition' => $pick('disposition'),
        ];
    }

    /** @param array<string, mixed> $report */
    private function isBrowserExtensionNoise(array $report): bool
    {
        foreach (['blocked_uri', 'source_file'] as $field) {
            $scheme = strtolower((string) parse_url((string) ($report[$field] ?? ''), PHP_URL_SCHEME));

            if (in_array($scheme, self::NOISE_SCHEMES, true)) {
                return true;
            }
        }

        return false;
    }
}
