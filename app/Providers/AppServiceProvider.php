<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use App\Mail\Transports\MicrosoftGraphTransport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Spatie Super-Admin Gate: Admin veya superadmin rolüne sahip kullanıcılar tüm yetki kontrollerinden doğrudan geçer
        Gate::before(function ($user, $ability) {
            if (method_exists($user, 'hasRole') && ($user->hasRole('Admin') || $user->hasRole('superadmin'))) {
                return true;
            }
        });

        // Custom Microsoft Graph Transport Kaydı
        Mail::extend('microsoft-graph', function (array $config) {
            return new MicrosoftGraphTransport(
                $config['tenant_id'],
                $config['client_id'],
                $config['client_secret'],
                env('MICROSOFT_FROM_ADDRESS')
            );
        });
    }
}
