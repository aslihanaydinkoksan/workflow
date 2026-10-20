<?php

declare(strict_types=1);

namespace App\Security\Listeners;

use App\Security\Config\LoginThrottleSettings;
use App\Security\Contracts\LoginAttemptGuard;
use App\Security\Contracts\RequestContextProvider;
use App\Security\Contracts\SecurityEventLogger;
use App\Security\Enums\SecurityEventType;
use App\Security\Logging\ValueSanitizer;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Events\Dispatcher;

/**
 * Hooks Laravel's built-in auth events, so any login flow that goes through
 * Auth::attempt() / Auth::validate() (Breeze, Jetstream, Fortify, UI, custom) is covered.
 */
final class AuthenticationEventSubscriber
{
    public function __construct(
        private readonly SecurityEventLogger $logger,
        private readonly LoginAttemptGuard $guard,
        private readonly LoginThrottleSettings $settings,
        private readonly RequestContextProvider $context,
        private readonly ValueSanitizer $sanitizer,
    ) {
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Failed::class => 'onFailed',
            Login::class => 'onLogin',
            Logout::class => 'onLogout',
            Lockout::class => 'onLockout',
            PasswordReset::class => 'onPasswordReset',
        ];
    }

    public function onFailed(Failed $event): void
    {
        $rawUsername = $event->credentials[$this->settings->usernameField] ?? '';
        $username = is_string($rawUsername) ? $rawUsername : '';
        $ip = $this->context->current()?->ip() ?? 'unknown';

        $state = $this->guard->registerFailure($username, $ip);

        $common = [
            'username' => $this->sanitizer->username($username),
            'auth_guard' => $event->guard,
        ];

        $this->logger->record(SecurityEventType::LoginFailed, $common + [
            // false = unknown username; many of these from one IP indicates enumeration or stuffing.
            'user_exists' => $event->user !== null,
            'account_attempts' => $state->accountAttempts,
            'ip_attempts' => $state->ipAttempts,
        ]);

        if ($state->accountLockedNow) {
            $this->logger->record(SecurityEventType::AccountLocked, $common + [
                'attempts' => $state->accountAttempts,
                'lock_seconds' => $state->lockSeconds,
            ]);
        }

        if ($state->ipBlockedNow) {
            $this->logger->record(SecurityEventType::IpBlocked, $common + [
                'attempts' => $state->ipAttempts,
                'lock_seconds' => $state->lockSeconds,
            ]);
        }
    }

    public function onLogin(Login $event): void
    {
        $username = $this->usernameOf($event->user);

        if ($username !== null) {
            $this->guard->registerSuccess($username);
        }

        $this->logger->record(SecurityEventType::LoginSucceeded, [
            'username' => $username !== null ? $this->sanitizer->username($username) : null,
            'authenticated_user_id' => $event->user->getAuthIdentifier(),
            'auth_guard' => $event->guard,
            'remember' => $event->remember,
        ]);
    }

    public function onLogout(Logout $event): void
    {
        $this->logger->record(SecurityEventType::Logout, [
            'authenticated_user_id' => $event->user?->getAuthIdentifier(),
            'auth_guard' => $event->guard,
        ]);
    }

    public function onLockout(Lockout $event): void
    {
        $raw = $event->request->input($this->settings->usernameField);

        $this->logger->record(SecurityEventType::FrameworkLockout, [
            'username' => is_string($raw) ? $this->sanitizer->username($raw) : null,
        ], request: $event->request);
    }

    public function onPasswordReset(PasswordReset $event): void
    {
        $this->logger->record(SecurityEventType::PasswordReset, [
            'authenticated_user_id' => $event->user->getAuthIdentifier(),
        ]);
    }

    private function usernameOf(Authenticatable $user): ?string
    {
        $value = data_get($user, $this->settings->usernameField);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
