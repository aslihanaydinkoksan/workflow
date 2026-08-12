<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use App\Models\Node;
use App\Observers\NodeObserver;
use Illuminate\Support\Facades\Mail;
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
