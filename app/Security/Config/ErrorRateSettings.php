<?php

declare(strict_types=1);

namespace App\Security\Config;

final readonly class ErrorRateSettings
{
    /**
     * @param list<int> $countedStatuses HTTP statuses that count toward the scanning threshold.
     */
    public function __construct(
        public int $threshold,
        public int $windowSeconds,
        public array $countedStatuses,
    ) {
        if ($threshold < 1 || $windowSeconds < 1) {
            throw new \InvalidArgumentException('Error-rate threshold and window must be positive integers.');
        }
    }

    public function counts(int $status): bool
    {
        return in_array($status, $this->countedStatuses, true);
    }

    /** @param array<string, mixed> $config */
    public static function fromArray(array $config): self
    {
        return new self(
            threshold: (int) ($config['threshold'] ?? 50),
            windowSeconds: (int) ($config['window_seconds'] ?? 60),
            countedStatuses: array_values(array_map('intval', (array) ($config['counted_statuses'] ?? [400, 401, 403, 404, 405, 419, 422, 429]))),
        );
    }
}
