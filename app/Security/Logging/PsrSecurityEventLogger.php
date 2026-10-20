<?php

declare(strict_types=1);

namespace App\Security\Logging;

use App\Security\Config\LoggingSettings;
use App\Security\Contracts\RequestContextProvider;
use App\Security\Contracts\SecurityEventLogger;
use App\Security\Enums\SecurityEventType;
use App\Security\Enums\Severity;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;

final class PsrSecurityEventLogger implements SecurityEventLogger
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly RequestContextProvider $context,
        private readonly ValueSanitizer $sanitizer,
        private readonly LoggingSettings $settings,
    ) {
    }

    public function record(
        SecurityEventType $type,
        array $attributes = [],
        ?Severity $severity = null,
        ?Request $request = null,
    ): void {
        try {
            $severity ??= $type->defaultSeverity();
            $request ??= $this->context->current();

            $base = [
                '@timestamp' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z'),
                'event_type' => $type->value,
                'event_category' => $type->category(),
                'severity' => $severity->value,
                'application' => $this->settings->application,
                'environment' => $this->settings->environment,
            ];

            $requestContext = $request !== null ? $this->context->describe($request) : [];

            // Base and request fields win over attributes so callers cannot spoof source_ip, severity, etc.
            $event = $base + $requestContext + $this->sanitizer->sanitizeAttributes($attributes);

            $this->logger->log($severity->logLevel(), $type->value, $event);
        } catch (\Throwable $e) {
            // Logging must never break the request. Fall back to the PHP error log.
            error_log(sprintf('[security-monitoring] failed to record %s: %s', $type->value, $e->getMessage()));
        }
    }
}
