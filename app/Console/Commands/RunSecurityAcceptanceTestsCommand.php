<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SecurityTestRunnerService;
use Illuminate\Console\Command;

final class RunSecurityAcceptanceTestsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'security:test-acceptance {--json : Çıktıyı ham JSON formatında döner}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'IT Bilgi Güvenliği 10 Kabul Testini çalıştırır ve SIEM log üretimini doğrular.';

    public function handle(SecurityTestRunnerService $runner): int
    {
        $report = $runner->runAllTests();

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return $report['passed_tests'] === $report['total_tests'] ? self::SUCCESS : self::FAILURE;
        }

        $this->info("====================================================================");
        $this->info("🛡️ IT BİLGİ GÜVENLİĞİ 10 KABUL TESTİ VE SIEM DOĞRULAMA SUITE'İ");
        $this->info("====================================================================");
        $this->line("Tarih: " . $report['executed_at']);
        $this->line("Süre : " . $report['duration_ms'] . " ms");
        $this->line("Sonuç: " . $report['passed_tests'] . " / " . $report['total_tests'] . " (% " . $report['success_rate'] . " Başarı)\n");

        foreach ($report['results'] as $r) {
            $status = $r['passed'] ? '<fg=green;options=bold>✅ GEÇTİ</>' : '<fg=red;options=bold>❌ BAŞARISIZ</>';
            $this->line(sprintf("  [%s] #%d %s (%s ms)", $status, $r['id'], $r['name'], $r['duration_ms']));
            $this->line("       Gereksinim: " . $r['requirement'] . " | Beklenen Olay: " . $r['expected_event']);
            $this->line("       Detay: " . $r['details'] . "\n");
        }

        $this->info("====================================================================");

        return $report['passed_tests'] === $report['total_tests'] ? self::SUCCESS : self::FAILURE;
    }
}
