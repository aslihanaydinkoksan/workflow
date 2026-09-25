<?php

namespace App\Console\Commands;

use App\Services\FollowUpService;
use Illuminate\Console\Command;

class ProcessFollowUpsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workflow:process-follow-ups';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Zamanı gelen numune takiplerini kontrol eder ve satışçılara hatırlatma e-postaları ile bildirimleri gönderir.';

    /**
     * Execute the console command.
     */
    public function handle(FollowUpService $followUpService): int
    {
        $this->info('Numune takip hatırlatma kontrolü başlatılıyor...');

        $processed = $followUpService->processScheduledFollowUps();

        $this->info("Toplam {$processed} adet numune takibi için bildirim ve e-posta tetiklendi.");

        return Command::SUCCESS;
    }
}
