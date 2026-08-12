<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendDueDateNotifications extends Command
{
    protected $signature = 'notifications:due-dates';

    protected $description = 'Süre dolmadan ve süre dolduktan sonra görev hatırlatmalarını gönderir';

    public function handle(NotificationService $notificationService): int
    {
        $count = $notificationService->sendDueDateReminders();

        $this->info("{$count} bildirim işlendi.");

        return self::SUCCESS;
    }
}
