<?php

declare(strict_types=1);

namespace App\Security\Config;

final readonly class CspSettings
{
    public function __construct(
        public bool $enabled,
        public bool $reportOnly,
        public string $policy,
        public string $reportPath,
    ) {
    }

    public function headerName(): string
    {
        return $this->reportOnly
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';
    }

    public function headerValue(): string
    {
        $policy = rtrim(trim($this->policy), ';');

        return $policy . '; report-uri /' . ltrim($this->reportPath, '/');
    }

    /** @param array<string, mixed> $config */
    public static function fromArray(array $config): self
    {
        return new self(
            enabled: (bool) ($config['enabled'] ?? true),
            reportOnly: (bool) ($config['report_only'] ?? true),
            policy: (string) ($config['policy'] ?? "default-src 'self'"),
            reportPath: (string) ($config['report_path'] ?? 'security/csp-report'),
        );
    }
}
