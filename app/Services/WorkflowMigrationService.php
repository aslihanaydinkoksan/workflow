<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProcessInstance;
use App\Models\Task;
use App\Models\Workflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sınıf Sorumluluğu: Fortune-500 ve Camunda standartlarında canlı çalışan süreç örneklerinin
 * kesintisiz (Zero-Downtime) yeni akış versiyonuna düğüm eşleştirmesiyle taşınmasını sağlar.
 */
class WorkflowMigrationService
{
    public function __construct(
        protected ProcessAuditService $auditService
    ) {}

    /**
     * İki iş akışı arasındaki düğüm farklarını analiz eder ve otomatik eşleştirme şeması üretir.
     */
    public function getMigrationSchema(Workflow $sourceWorkflow, Workflow $targetWorkflow): array
    {
        $sourceNodes = $sourceWorkflow->nodes ?? [];
        $targetNodes = $targetWorkflow->nodes ?? [];

        // Kaynak akıştaki aktif süreçler ve bekleyen görevler
        $activeInstances = ProcessInstance::where('workflow_id', $sourceWorkflow->id)
            ->whereIn('status', ['running', 'waiting'])
            ->get();

        $activeInstanceIds = $activeInstances->pluck('id')->all();

        $pendingTasksCountByNode = Task::whereIn('process_instance_id', $activeInstanceIds)
            ->where('status', 'pending')
            ->select('node_id', DB::raw('count(*) as count'))
            ->groupBy('node_id')
            ->pluck('count', 'node_id')
            ->all();

        $targetNodeMap = [];
        foreach ($targetNodes as $tn) {
            $targetNodeMap[$tn['id']] = $tn;
        }

        $formattedSourceNodes = [];
        $suggestedMapping = [];

        foreach ($sourceNodes as $sn) {
            $nodeId = $sn['id'];
            $label = $sn['data']['customName'] ?? $sn['data']['label'] ?? $nodeId;
            $taskType = $sn['data']['taskType'] ?? $sn['type'] ?? 'task';
            $activeCount = $pendingTasksCountByNode[$nodeId] ?? 0;

            $formattedSourceNodes[] = [
                'id'                 => $nodeId,
                'label'              => $label,
                'task_type'          => $taskType,
                'active_tasks_count' => $activeCount,
            ];

            // Akıllı Eşleştirme Önerisi
            if (isset($targetNodeMap[$nodeId])) {
                // 1. Birebir ID eşleşmesi
                $suggestedMapping[$nodeId] = $nodeId;
            } else {
                // 2. İsim benzerliği eşleşmesi
                $matchedTarget = null;
                foreach ($targetNodes as $tn) {
                    $tLabel = $tn['data']['customName'] ?? $tn['data']['label'] ?? '';
                    if (mb_strtolower($tLabel, 'UTF-8') === mb_strtolower($label, 'UTF-8')) {
                        $matchedTarget = $tn['id'];
                        break;
                    }
                }

                // 3. Görev tipi benzerliği eşleşmesi
                if (!$matchedTarget) {
                    foreach ($targetNodes as $tn) {
                        $tType = $tn['data']['taskType'] ?? $tn['type'] ?? '';
                        if ($tType === $taskType) {
                            $matchedTarget = $tn['id'];
                            break;
                        }
                    }
                }

                $suggestedMapping[$nodeId] = $matchedTarget ?: ($targetNodes[0]['id'] ?? null);
            }
        }

        $formattedTargetNodes = [];
        foreach ($targetNodes as $tn) {
            $formattedTargetNodes[] = [
                'id'        => $tn['id'],
                'label'     => $tn['data']['customName'] ?? $tn['data']['label'] ?? $tn['id'],
                'task_type' => $tn['data']['taskType'] ?? $tn['type'] ?? 'task',
            ];
        }

        return [
            'source_workflow' => [
                'id'                     => $sourceWorkflow->id,
                'name'                   => $sourceWorkflow->name,
                'version'                => $sourceWorkflow->version ?: 1,
                'active_instances_count' => count($activeInstances),
            ],
            'target_workflow' => [
                'id'      => $targetWorkflow->id,
                'name'    => $targetWorkflow->name,
                'version' => $targetWorkflow->version ?: 1,
            ],
            'source_nodes'      => $formattedSourceNodes,
            'target_nodes'      => $formattedTargetNodes,
            'suggested_mapping' => $suggestedMapping,
        ];
    }

    /**
     * Aktif süreçleri eski akış versiyonundan yeni versiyona canlı ve kesintisiz göç ettirir.
     *
     * @param Workflow $sourceWorkflow Kaynak iş akışı (Örn: v1.0)
     * @param Workflow $targetWorkflow Hedef iş akışı (Örn: v2.0)
     * @param array<string, string> $nodeMapping [kaynak_dugum_id => hedef_dugum_id]
     * @param array<int>|null $instanceIds Belirli süreçleri taşımak için ID listesi (null ise tümü)
     * @param int|null $adminUserId İşlemi yapan yetkili
     * @return array{migrated_instances_count: int, updated_tasks_count: int}
     */
    public function migrateInstances(
        Workflow $sourceWorkflow,
        Workflow $targetWorkflow,
        array $nodeMapping,
        ?array $instanceIds = null,
        ?int $adminUserId = null
    ): array {
        return DB::transaction(function () use ($sourceWorkflow, $targetWorkflow, $nodeMapping, $instanceIds, $adminUserId) {
            $query = ProcessInstance::where('workflow_id', $sourceWorkflow->id)
                ->whereIn('status', ['running', 'waiting']);

            if (!empty($instanceIds)) {
                $query->whereIn('id', $instanceIds);
            }

            $instances = $query->get();
            $targetNodes = collect($targetWorkflow->nodes ?? []);

            $migratedInstancesCount = 0;
            $updatedTasksCount = 0;

            foreach ($instances as $instance) {
                $pendingTasks = Task::where('process_instance_id', $instance->id)
                    ->where('status', 'pending')
                    ->get();

                $oldNodeId = $instance->current_node_id;
                $newNodeId = $oldNodeId;

                foreach ($pendingTasks as $task) {
                    $mappedTargetNodeId = $nodeMapping[$task->node_id] ?? null;
                    if ($mappedTargetNodeId && $mappedTargetNodeId !== $task->node_id) {
                        $targetNodeDef = $targetNodes->firstWhere('id', $mappedTargetNodeId);
                        $newTitle = $targetNodeDef['data']['customName']
                            ?? $targetNodeDef['data']['label']
                            ?? $task->title;

                        $task->update([
                            'node_id' => $mappedTargetNodeId,
                        ]);
                        $updatedTasksCount++;
                    }
                }

                if ($instance->current_node_id && isset($nodeMapping[$instance->current_node_id])) {
                    $newNodeId = $nodeMapping[$instance->current_node_id];
                }

                // Süreci yeni versiyona güncelle
                $instance->update([
                    'workflow_id'     => $targetWorkflow->id,
                    'current_node_id' => $newNodeId,
                ]);

                // Git-stili Alan Bazlı Denetim İzi Kaydı
                $this->auditService->recordAdminIntervention(
                    $instance,
                    'workflow_migration',
                    $adminUserId ?: auth()->id(),
                    'BPMN 2.0 Canlı Süreç Versiyon Göçü (Schema Migration)',
                    "v{$sourceWorkflow->version} ({$oldNodeId})",
                    "v{$targetWorkflow->version} ({$newNodeId})",
                    "Süreç canlı çalışırken kesintisiz (zero-downtime) v{$targetWorkflow->version} mimarisine taşındı. Aktif görevler hedef düğüm şemasıyla senkronize edildi."
                );

                $migratedInstancesCount++;
            }

            Log::info("Canlı Süreç Göçü Başarılı: {$migratedInstancesCount} süreç v{$sourceWorkflow->version} -> v{$targetWorkflow->version} taşındı.");

            return [
                'migrated_instances_count' => $migratedInstancesCount,
                'updated_tasks_count'      => $updatedTasksCount,
                'source_version'           => $sourceWorkflow->version ?: 1,
                'target_version'           => $targetWorkflow->version ?: 1,
            ];
        });
    }
}
