<?php

declare(strict_types=1);

namespace App\Security\Config;

final readonly class LoggingSettings
{
    /**
     * @param list<string> $sensitiveFields Lower-cased attribute names whose values are always redacted.
     */
    public function __construct(
        public string $application,
        public string $environment,
        public int $maxValueLength,
        public bool $pseudonymizeUsernames,
        public string $pseudonymizationKey,
        public array $sensitiveFields,
    ) {
        if ($pseudonymizeUsernames && $pseudonymizationKey === '') {
            throw new \InvalidArgumentException('A pseudonymization key is required when username pseudonymization is enabled.');
        }
    }

    /**
     * @param array<string, mixed> $config
     * @param list<string> $sensitiveFields
     */
    public static function fromArray(array $config, array $sensitiveFields, string $environment, string $appKey): self
    {
        return new self(
            application: (string) ($config['application'] ?? 'laravel'),
            environment: $environment,
            maxValueLength: max(32, (int) ($config['max_value_length'] ?? 200)),
            pseudonymizeUsernames: (bool) ($config['pseudonymize_usernames'] ?? false),
            pseudonymizationKey: $appKey,
            sensitiveFields: array_values(array_map('strtolower', $sensitiveFields)),
        );
    }
}
