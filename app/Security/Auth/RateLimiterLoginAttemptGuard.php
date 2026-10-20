<?php

declare(strict_types=1);

namespace App\Security\Auth;

use App\Security\Config\LoginThrottleSettings;
use App\Security\Contracts\LoginAttemptGuard;
use Illuminate\Cache\RateLimiter;

/**
 * Two independent budgets:
 *  - per account: stops password guessing against one user from many IPs;
 *  - per source IP: stops one source spraying many usernames (credential stuffing).
 *
 * Backed by the cache store, so in multi-server deployments the cache must be shared (Redis).
 */
final class RateLimiterLoginAttemptGuard implements LoginAttemptGuard
{
    private const ACCOUNT_PREFIX = 'security:login:account:';
    private const IP_PREFIX = 'security:login:ip:';

    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly LoginThrottleSettings $settings,
    ) {
    }

    public function secondsUntilAllowed(string $username, string $ip): int
    {
        $wait = 0;

        if ($this->limiter->tooManyAttempts($this->accountKey($username), $this->settings->maxAccountAttempts)) {
            $wait = max($wait, $this->limiter->availableIn($this->accountKey($username)));
        }

        if ($this->limiter->tooManyAttempts($this->ipKey($ip), $this->settings->maxIpAttempts)) {
            $wait = max($wait, $this->limiter->availableIn($this->ipKey($ip)));
        }

        return $wait;
    }

    public function registerFailure(string $username, string $ip): LoginFailureState
    {
        $accountAttempts = $this->limiter->hit($this->accountKey($username), $this->settings->decaySeconds);
        $ipAttempts = $this->limiter->hit($this->ipKey($ip), $this->settings->decaySeconds);

        return new LoginFailureState(
            accountAttempts: $accountAttempts,
            ipAttempts: $ipAttempts,
            // Fires when threshold is reached or exceeded
            accountLockedNow: $accountAttempts >= $this->settings->maxAccountAttempts,
            ipBlockedNow: $ipAttempts >= $this->settings->maxIpAttempts,
            lockSeconds: $this->settings->decaySeconds,
        );
    }

    public function registerSuccess(string $username): void
    {
        $this->limiter->clear($this->accountKey($username));
    }

    private function accountKey(string $username): string
    {
        return self::ACCOUNT_PREFIX . hash('sha256', mb_strtolower(trim($username)));
    }

    private function ipKey(string $ip): string
    {
        return self::IP_PREFIX . $ip;
    }
}
