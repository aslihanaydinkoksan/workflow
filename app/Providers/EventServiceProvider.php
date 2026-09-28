<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\MailGuardListener;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application events.
     */
    public function boot(): void
    {
        Event::listen(
            MessageSending::class,
            MailGuardListener::class
        );
    }
}
