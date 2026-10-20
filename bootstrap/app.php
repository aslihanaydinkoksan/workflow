<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        $schedule->command('notifications:due-dates')->everyFifteenMinutes();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // En dış katmanda istek korelasyon ID'si (X-Request-Id) atanır
        $middleware->prepend(\App\Security\Http\Middleware\AssignRequestId::class);

        // Güvenlik izleme, saldırı imza denetimi ve CSP başlığı
        $middleware->append([
            \App\Security\Http\Middleware\MonitorSecurityResponses::class,
            \App\Security\Http\Middleware\InspectRequestPayload::class,
            \App\Security\Http\Middleware\ContentSecurityPolicy::class,
        ]);

        // Login brute force koruması middleware takma adı
        $middleware->alias([
            'security.login' => \App\Security\Http\Middleware\ThrottleLoginAttempts::class,
        ]);

        // WAF / Reverse Proxy arkasındaki gerçek istemci IP'si için TrustProxies
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: array_map('trim', explode(',', (string) $proxies)));
        }

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'dev/*',
            'security/csp-report',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
