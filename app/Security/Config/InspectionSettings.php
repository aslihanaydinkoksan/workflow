<?php

declare(strict_types=1);

namespace App\Security\Config;

final readonly class InspectionSettings
{
    public const MODE_MONITOR = 'monitor';
    public const MODE_BLOCK = 'block';

    /**
     * @param list<string> $excludedFields Lower-cased field names never inspected (e.g. WYSIWYG content).
     * @param list<string> $excludedPaths Request::is() patterns that are skipped entirely.
     * @param list<string> $sensitiveFields Lower-cased field names never inspected nor logged.
     */
    public function __construct(
        public bool $enabled,
        public string $mode,
        public array $excludedFields,
        public array $excludedPaths,
        public array $sensitiveFields,
        public int $maxInspectLength,
    ) {
        if (!in_array($mode, [self::MODE_MONITOR, self::MODE_BLOCK], true)) {
            throw new \InvalidArgumentException("Invalid inspection mode [{$mode}]. Use 'monitor' or 'block'.");
        }
    }

    public function blocks(): bool
    {
        return $this->enabled && $this->mode === self::MODE_BLOCK;
    }

    public function isFieldExcluded(string $field): bool
    {
        $name = strtolower($field);

        return in_array($name, $this->excludedFields, true) || $this->isSensitive($name);
    }

    public function isSensitive(string $field): bool
    {
        return in_array(strtolower($field), $this->sensitiveFields, true);
    }

    /**
     * @param array<string, mixed> $config
     * @param list<string> $sensitiveFields
     */
    public static function fromArray(array $config, array $sensitiveFields): self
    {
        return new self(
            enabled: (bool) ($config['enabled'] ?? true),
            mode: (string) ($config['mode'] ?? self::MODE_MONITOR),
            excludedFields: array_values(array_map('strtolower', (array) ($config['excluded_fields'] ?? []))),
            excludedPaths: array_values((array) ($config['excluded_paths'] ?? [])),
            sensitiveFields: array_values(array_map('strtolower', $sensitiveFields)),
            maxInspectLength: max(256, (int) ($config['max_inspect_length'] ?? 8192)),
        );
    }
}
