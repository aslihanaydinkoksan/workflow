<?php

declare(strict_types=1);

namespace App\Security\Auth;

final readonly class LoginFailureState
{
    public function __construct(
        public int $accountAttempts,
        public int $ipAttempts,
        public bool $accountLockedNow,
        public bool $ipBlockedNow,
        public int $lockSeconds,
    ) {
    }
}
