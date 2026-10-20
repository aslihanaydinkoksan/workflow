<?php

declare(strict_types=1);

namespace App\Providers;

use App\Security\Auth\RateLimiterLoginAttemptGuard;
use App\Security\Config\CspSettings;
use App\Security\Config\ErrorRateSettings;
use App\Security\Config\InspectionSettings;
use App\Security\Config\LoggingSettings;
use App\Security\Config\LoginThrottleSettings;
use App\Security\Contracts\LoginAttemptGuard;
use App\Security\Contracts\RequestContextProvider;
use App\Security\Contracts\SecurityEventLogger;
use App\Security\Detection\InputNormalizer;
use App\Security\Detection\PayloadInspector;
use App\Security\Detection\ScannerUserAgentDetector;
use App\Security\Detection\SqlInjectionDetector;
use App\Security\Detection\XssDetector;
use App\Security\Http\Controllers\CspReportController;
use App\Security\Listeners\AuthenticationEventSubscriber;
use App\Security\Logging\LaravelRequestContextProvider;
use App\Security\Logging\PsrSecurityEventLogger;
use App\Security\Logging\ValueSanitizer;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

final class SecurityMonitoringServiceProvider extends ServiceProvider
{
    public const DETECTOR_TAG = 'security.payload_detectors';

    private const CONFIG = 'security-monitoring';

    public function register(): void
    {
        $this->registerSettings();

        $this->app->singleton(ValueSanitizer::class);
        $this->app->singleton(InputNormalizer::class);
        $this->app->singleton(ScannerUserAgentDetector::class);
        $this->app->singleton(RequestContextProvider::class, LaravelRequestContextProvider::class);
        $this->app->singleton(LoginAttemptGuard::class, RateLimiterLoginAttemptGuard::class);

        $this->app->singleton(SecurityEventLogger::class, PsrSecurityEventLogger::class);
        $this->app->when(PsrSecurityEventLogger::class)
            ->needs(LoggerInterface::class)
            ->give(fn (Application $app): LoggerInterface => $app->make('log')->channel(
                (string) $this->config($app, 'log_channel', 'security'),
            ));

        // New detectors (e.g. path traversal) only need to be tagged here: open for extension.
        $this->app->singleton(SqlInjectionDetector::class);
        $this->app->singleton(XssDetector::class);
        $this->app->tag([SqlInjectionDetector::class, XssDetector::class], self::DETECTOR_TAG);

        $this->app->singleton(PayloadInspector::class, fn (Application $app): PayloadInspector => new PayloadInspector(
            $app->tagged(self::DETECTOR_TAG),
            $app->make(InspectionSettings::class),
        ));
    }

    public function boot(Dispatcher $events, Router $router, CspSettings $csp, Config $config): void
    {
        $events->subscribe(AuthenticationEventSubscriber::class);

        if ($csp->enabled && !$router->has('security.csp-report') && !$this->app->routesAreCached()) {
            $limit = max(1, (int) $config->get(self::CONFIG . '.csp.report_rate_limit', 120));

            // Registered outside the "web" group on purpose: browsers post reports without
            // session cookies or CSRF tokens.
            $router->post($csp->reportPath, CspReportController::class)
                ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])
                ->middleware("throttle:{$limit},1")
                ->name('security.csp-report');
        }
    }

    private function registerSettings(): void
    {
        $this->app->singleton(LoggingSettings::class, fn (Application $app): LoggingSettings => LoggingSettings::fromArray(
            (array) $this->config($app, 'logging', []),
            $this->sensitiveFields($app),
            (string) $app->environment(),
            (string) $app->make(Config::class)->get('app.key', ''),
        ));

        $this->app->singleton(LoginThrottleSettings::class, fn (Application $app): LoginThrottleSettings => LoginThrottleSettings::fromArray(
            (array) $this->config($app, 'login_throttle', []),
        ));

        $this->app->singleton(ErrorRateSettings::class, fn (Application $app): ErrorRateSettings => ErrorRateSettings::fromArray(
            (array) $this->config($app, 'error_rate', []),
        ));

        $this->app->singleton(CspSettings::class, fn (Application $app): CspSettings => CspSettings::fromArray(
            (array) $this->config($app, 'csp', []),
        ));

        $this->app->singleton(InspectionSettings::class, function (Application $app): InspectionSettings {
            $inspection = (array) $this->config($app, 'inspection', []);

            // CSP reports legitimately contain script samples; inspecting them would only create noise.
            $reportPath = ltrim((string) $this->config($app, 'csp.report_path', 'security/csp-report'), '/');
            $inspection['excluded_paths'] = [...(array) ($inspection['excluded_paths'] ?? []), $reportPath];

            return InspectionSettings::fromArray($inspection, $this->sensitiveFields($app));
        });
    }

    /**
     * @return list<string>
     */
    private function sensitiveFields(Application $app): array
    {
        return array_values(array_map('strval', (array) $this->config($app, 'sensitive_fields', ['password'])));
    }

    private function config(Application $app, string $key, mixed $default): mixed
    {
        return $app->make(Config::class)->get(self::CONFIG . '.' . $key, $default);
    }
}
