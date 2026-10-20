<?php

declare(strict_types=1);

namespace App\Security\Contracts;

use App\Security\Auth\LoginFailureState;

interface LoginAttemptGuard
{
    /**
     * Seconds until the account or the source IP may try again; 0 when allowed.
     */
    public function secondsUntilAllowed(string $username, string $ip): int;

    public function registerFailure(string $username, string $ip): LoginFailureState;

    /**
     * Resets the per-account counter only. The per-IP counter is deliberately kept,
     * so an attacker who owns one valid account cannot use it to reset the IP budget.
     */
    public function registerSuccess(string $username): void;
}
