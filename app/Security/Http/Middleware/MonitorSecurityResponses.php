<?php

declare(strict_types=1);

namespace App\Security\Http\Middleware;

use App\Security\Config\ErrorRateSettings;
use App\Security\Contracts\SecurityEventLogger;
use App\Security\Enums\SecurityEventType;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Cache\RateLimiter;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Global middleware, registered as the outermost security layer. Laravel attaches the
 * exception that produced an error response to $response->exception, which lets us see
 * validation, authorization, CSRF and database failures that the default exception
 * handler deliberately does not log — without touching bootstrap/app.php exception config.
 */
final class MonitorSecurityResponses
{
    private const MAX_FIELDS = 20;

    /**
     * SQLSTATE values that normal traffic should not produce but injection probes do:
     * class 42 = syntax error / access rule violation, 21 = cardinality (UNION column
     * mismatch, multi-row subquery), 22P02 = PostgreSQL invalid text representation.
     */
    private const SUSPICIOUS_SQLSTATE_CLASSES = ['42', '21'];
    private const SUSPICIOUS_SQLSTATES = ['22P02', '22018'];

    public function __construct(
        private readonly SecurityEventLogger $logger,
        private readonly ErrorRateSettings $errorRate,
        private readonly RateLimiter $limiter,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $this->inspect($request, $response);
        } catch (\Throwable $e) {
            error_log('[security-monitoring] response inspection failed: ' . $e->getMessage());
        }

        return $response;
    }

    private function inspect(Request $request, Response $response): void
    {
        $status = $response->getStatusCode();

        if (!$request->attributes->get(ThrottleLoginAttempts::HANDLED_ATTRIBUTE, false)) {
            $exception = property_exists($response, 'exception') ? $response->exception : null;
            $this->recordException($request, $status, $exception instanceof \Throwable ? $exception : null);
        }

        $this->trackErrorRate($request, $status);
    }

    private function recordException(Request $request, int $status, ?\Throwable $e): void
    {
        match (true) {
            $e instanceof ValidationException => $this->logger->record(SecurityEventType::ValidationFailed, [
                'status' => $status,
                // Field names only; submitted values may contain personal data.
                'fields' => array_slice(array_keys($e->errors()), 0, self::MAX_FIELDS),
            ], request: $request),

            $e instanceof TokenMismatchException => $this->logger->record(SecurityEventType::CsrfTokenMismatch, [
                'status' => $status,
            ], request: $request),

            $e instanceof ThrottleRequestsException => $this->logger->record(SecurityEventType::RateLimited, [
                'status' => $status,
            ], request: $request),

            $e instanceof QueryException => $this->recordDatabaseError($request, $e),

            $e instanceof AuthorizationException,
            $e instanceof AccessDeniedHttpException,
            $status === Response::HTTP_FORBIDDEN => $this->logger->record(SecurityEventType::AccessDenied, [
                'status' => $status,
                'reason' => $e !== null ? $e::class : null,
            ], request: $request),

            default => null,
        };
    }

    private function recordDatabaseError(Request $request, QueryException $e): void
    {
        $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());
        $driverMessage = (string) ($e->errorInfo[2] ?? '');

        $suspicious = in_array(substr($sqlState, 0, 2), self::SUSPICIOUS_SQLSTATE_CLASSES, true)
            || in_array($sqlState, self::SUSPICIOUS_SQLSTATES, true)
            // SQLite reports syntax errors as generic HY000.
            || ($sqlState === 'HY000' && stripos($driverMessage, 'syntax error') !== false);

        $this->logger->record(
            $suspicious ? SecurityEventType::DatabaseSyntaxError : SecurityEventType::DatabaseError,
            [
                'sqlstate' => $sqlState,
                'driver_code' => $e->errorInfo[1] ?? null,
                'driver_message' => $driverMessage,
                'connection' => $e->getConnectionName(),
                // Statement template only. Bindings are never logged; if the app concatenates
                // input into SQL, the injected payload will be visible here, which is the point.
                'sql' => $e->getSql(),
            ],
            request: $request,
        );
    }

    private function trackErrorRate(Request $request, int $status): void
    {
        if (!$this->errorRate->counts($status)) {
            return;
        }

        $hits = $this->limiter->hit('security:error-rate:' . $request->ip(), $this->errorRate->windowSeconds);

        // Fires once per window when the threshold is crossed, not on every subsequent error.
        if ($hits === $this->errorRate->threshold) {
            $this->logger->record(SecurityEventType::ScanningBehavior, [
                'error_responses' => $hits,
                'window_seconds' => $this->errorRate->windowSeconds,
                'last_status' => $status,
            ], request: $request);
        }
    }
}
