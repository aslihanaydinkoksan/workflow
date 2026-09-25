<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Numune Takiplerini Günlük Olarak Kontrol Et ve Hatırlatmaları Gönder
Schedule::command('workflow:process-follow-ups')->dailyAt('09:00');

// Görev Süre Hatırlatmaları
Schedule::command('notifications:due-dates')->hourly();

