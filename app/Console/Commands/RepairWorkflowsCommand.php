<?php

namespace App\Console\Commands;

use App\Services\WorkflowRepairService;
use Illuminate\Console\Command;

class RepairWorkflowsCommand extends Command
{
    protected $signature = 'workflows:repair {--keep-drafts : Taslak akışları otomatik yayınlama}';

    protected $description = 'Akış kenarlarını düzeltir, geçerli taslakları yayınlar ve takılı süreçleri ilerletir';

    public function handle(WorkflowRepairService $repairService, \App\Services\ProcessRecoveryService $recoveryService): int
    {
        $publishDrafts = ! $this->option('keep-drafts');
        $stats = $repairService->repairAll($publishDrafts);
        $recovered = $recoveryService->recoverStuckInstances();

        $this->info("Düzeltilen: {$stats['repaired']}");
        $this->info("Yayınlanan taslak: {$stats['published']}");
        $this->info("Değişmeyen: {$stats['skipped']}");
        $this->info("İlerletilen takılı süreç: {$recovered}");

        return self::SUCCESS;
    }
}
