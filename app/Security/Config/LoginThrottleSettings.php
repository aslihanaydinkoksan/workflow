<?php

declare(strict_types=1);

namespace App\Security\Config;

final readonly class LoginThrottleSettings
{
    public function __construct(
        public string $usernameField,
        public int $maxAccountAttempts,
        public int $maxIpAttempts,
        public int $decaySeconds,
    ) {
        if ($maxAccountAttempts < 1 || $maxIpAttempts < 1 || $decaySeconds < 1) {
            throw new \InvalidArgumentException('Login throttle limits must be positive integers.');
        }
    }

    /** @param array<string, mixed> $config */
    public static function fromArray(array $config): self
    {
        return new self(
            usernameField: (string) ($config['username_field'] ?? 'email'),
            maxAccountAttempts: (int) ($config['max_account_attempts'] ?? 5),
            maxIpAttempts: (int) ($config['max_ip_attempts'] ?? 20),
            decaySeconds: (int) ($config['decay_seconds'] ?? 900),
        );
    }
}
