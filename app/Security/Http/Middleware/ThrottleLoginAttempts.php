<?php

declare(strict_types=1);

namespace App\Security\Http\Middleware;

use App\Security\Config\LoginThrottleSettings;
use App\Security\Contracts\LoginAttemptGuard;
use App\Security\Contracts\SecurityEventLogger;
use App\Security\Enums\SecurityEventType;
use App\Security\Logging\ValueSanitizer;
use Closure;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Attach to the login POST route (and any API token endpoint). Rejects the attempt
 * before credentials are checked when the account or source IP is over budget.
 * Failures themselves are counted by AuthenticationEventSubscriber.
 */
final class ThrottleLoginAttempts
{
    public const HANDLED_ATTRIBUTE = 'security.event_already_logged';

    public function __construct(
        private readonly LoginAttemptGuard $guard,
        private readonly LoginThrottleSettings $settings,
        private readonly SecurityEventLogger $logger,
        private readonly ValueSanitizer $sanitizer,
        private readonly Translator $translator,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->isMethod('POST')) {
            return $next($request);
        }

        $raw = $request->input($this->settings->usernameField);
        $username = is_string($raw) ? $raw : '';
        $wait = $this->guard->secondsUntilAllowed($username, (string) $request->ip());

        if ($wait > 0) {
            $this->logger->record(SecurityEventType::LoginBlocked, [
                'username' => $this->sanitizer->username($username),
                'retry_after_seconds' => $wait,
            ], request: $request);

            $request->attributes->set(self::HANDLED_ATTRIBUTE, true);

            $msg = $this->translator->get('auth.throttle', [
                'seconds' => $wait,
                'minutes' => (int) ceil($wait / 60),
            ]);

            $jsonResponse = response()->json([
                'message' => $msg,
                'errors' => [
                    $this->settings->usernameField => [$msg],
                ],
                'retry_after' => $wait,
            ], Response::HTTP_TOO_MANY_REQUESTS);

            $exception = ValidationException::withMessages([
                $this->settings->usernameField => $msg,
            ])->status(Response::HTTP_TOO_MANY_REQUESTS);

            $exception->response = $jsonResponse;

            throw $exception;
        }

        return $next($request);
    }
}
