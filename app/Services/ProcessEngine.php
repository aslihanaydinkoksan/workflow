<?php

namespace App\Services;

use App\Models\ProcessInstance;
use App\Models\Workflow;
use App\Models\Node as HierarchyNode;
use Illuminate\Support\Facades\Log;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Mail;
use App\Mail\ProcessNotificationMail;
use App\Services\RuleAction;

/**
 * Sınıf Sorumluluğu: Durum makinesi (State Machine) mimarisinde süreç akışlarını yönetir,
 * hiyerarşi motoru verileri ile kural motorunu (RulesEngine) konuşturarak akıllı kararlar üretir.
 */
class ProcessEngine
{
    private RulesEngine $rulesEngine;
    private TaskManager $taskManager;

    public function __construct(RulesEngine $rulesEngine, TaskManager $taskManager)
    {
        $this->rulesEngine = $rulesEngine;
        $this->taskManager = $taskManager;
    }

    public function startProcess(Workflow $workflow, int $userId, array $initialData): ProcessInstance
    {
        $graph = $this->normalizedGraph($workflow);
        $startNode = $this->findStartNode($graph['nodes']);

        $instance = ProcessInstance::create([
            'workflow_id'     => $workflow->id,
            'status'          => 'running',
            'started_by'      => $userId,
            'data'            => $initialData,
            'current_node_id' => $startNode['id'] ?? null,
        ]);

        if ($instance->current_node_id) {
            $this->advance($instance);
        }

        return $instance;
    }

    public function onTaskCompleted(ProcessInstance $instance, string $action = 'approve'): void
    {
        $instance->update(['status' => 'running']);
        $this->advance($instance, $action);
    }

    /**
     * Süreç akışını bir sonraki düğüme taşır ve hiyerarşik kuralları tetikler.
     */
    public function advance(ProcessInstance $instance, string $action = 'approve'): void
    {
        // VERİ TAZELİĞİ KORUMASI: Bellekteki bayat veriyi ez
        $instance->refresh();

        if ($instance->status !== 'running') {
            return;
        }

        $workflow = $instance->workflow;
        $graph = $this->normalizedGraph($workflow);
        $currentNodeId = $instance->current_node_id;
        $node = $this->getNodeById($graph['nodes'], $currentNodeId);

        if (!$node) {
            return;
        }

        // BİTİŞ (END) DÜĞÜMÜ KONTROLÜ
        if ($node['type'] === 'output' || ($node['data']['taskType'] ?? '') === 'end') {
            if ($this->taskManager->instanceHasPendingTasks($instance)) {
                $instance->update(['status' => 'waiting']);
                return;
            }

            // GÜÇLÜ KORUMA (ZEHİRLİ HAP): Süreç herhangi bir aşamada iptal/ret yemişse mühür kontrolü yap
            $isForceRejected = $instance->data['_force_rejected'] ?? false;

            if ($isForceRejected) {
                $finalStatus = 'rejected';
            } else {
                $nodeStatus = $node['data']['processStatus'] ?? null;
                if ($nodeStatus) {
                    $finalStatus = $nodeStatus;
                } else {
                    $finalStatus = in_array($instance->status, ['rejected', 'cancelled'], true)
                        ? $instance->status
                        : 'completed';
                }
            }

            $instance->update(['status' => $finalStatus]);
            if ($finalStatus === 'completed') {
                $this->handleProcessCompleted($instance);
            }
            return;
        }

        $context = $this->buildExtendedContext($instance);

        $ruleAction = null;
        if (($node['data']['taskType'] ?? '') === 'system_rule') {
            $ruleAction = $this->rulesEngine->evaluateNodeRules($workflow->id, $currentNodeId, $context);
        }

        $nextEdges = [];
        if ($ruleAction) {
            $targetNodeId = $ruleAction->params['target_node_id'] ?? null;
            $reason = $ruleAction->params['reason'] ?? 'Talebiniz sistem kurallarına uymadığı için farklı bir akışa yönlendirilmiştir.';

            // Bildirimler
            if ($instance->started_by && $instance->starter && $instance->starter->email) {
                UserNotification::create([
                    'user_id' => $instance->started_by,
                    'type'    => 'system',
                    'title'   => 'Süreç Otomatik Yönlendirildi / Reddedildi',
                    'body'    => $reason,
                    'data'    => [
                        'url'                 => route('processes.tracker', $instance->id),
                        'action_url'          => route('processes.tracker', $instance->id),
                        'process_instance_id' => $instance->id,
                        'reason'              => $reason,
                    ],
                ]);

                // E-Posta İçin Veri
                $mailData = [
                    'description' => "Talebinizle ilgili kural motoru tarafından otomatik bir karar alınmıştır.\n\n**Sistem Karar Notu:** {$reason}",
                ];

                Mail::to($instance->starter->email)
                    ->queue(new ProcessNotificationMail($instance, $mailData));
            }

            $nextEdges[] = ['target' => $targetNodeId];
        }

        if (!$ruleAction) {
            $edges = collect($graph['edges'])->where('source', $currentNodeId);
            $normalizedAction = $this->normalizeEdgeAction($action);

            if ($this->supportsActionBranching($node)) {
                $matched = $edges->filter(function ($edge) use ($action, $normalizedAction) {
                    $handle = $edge['sourceHandle'] ?? null;
                    $condition = $edge['data']['condition'] ?? null;

                    return in_array($handle, [$action, $normalizedAction], true)
                        || in_array($condition, [$action, $normalizedAction], true);
                });

                if ($matched->isNotEmpty()) {
                    $edges = $matched;
                }
            }

            if (empty($nextEdges)) {
                $nextEdges = $edges->all();
            }
        }

        // Çıkmaz sokak ise bitir
        if (empty($nextEdges)) {
            if ($this->taskManager->instanceHasPendingTasks($instance)) {
                $instance->update(['status' => 'waiting']);
                return;
            }

            $isForceRejected = $instance->data['_force_rejected'] ?? false;
            if ($isForceRejected) {
                $finalStatus = 'rejected';
            } else {
                $nodeStatus = $node['data']['processStatus'] ?? null;
                $finalStatus = $nodeStatus ?: (in_array($instance->status, ['rejected', 'cancelled'], true) ? $instance->status : 'completed');
            }
            $instance->update(['status' => $finalStatus]);
            if ($finalStatus === 'completed') {
                $this->handleProcessCompleted($instance);
            }
            return;
        }

        $humanBranches = [];
        $autoBranches = [];

        foreach ($nextEdges as $edge) {
            $nextNodeId = $edge['target'];
            $nextNode = $this->getNodeById($graph['nodes'], $nextNodeId);

            if (!$nextNode) {
                continue;
            }

            // --- EVRENSEL RET YAKALAYICI (Kelime Sınırı Güvenliği: 'üretim' gibi kelimelerin 'ret' ile çakışmasını önler) ---
            $customName = $nextNode['data']['customName'] ?? '';
            $label = $nextNode['data']['label'] ?? '';
            $fullNodeName = mb_strtolower($customName . ' ' . $label, 'UTF-8');
            $normalizedName = str_replace(['ı', 'i̇', 'ğ', 'ü', 'ş', 'ö', 'ç'], ['i', 'i', 'g', 'u', 's', 'o', 'c'], $fullNodeName);

            $isRejectAction = ($action === 'rejected') || (($ruleAction->type ?? '') === 'reject_and_route');
            $isRejectNode = preg_match('/\b(iptal|red|ret|reddedildi|reddedilme)\b/i', $normalizedName) === 1;

            if ($isRejectAction || $isRejectNode) {
                $data = $instance->data ?? [];
                $data['_force_rejected'] = true;
                $instance->data = $data;
                $instance->save();
            }

            $taskType = $nextNode['data']['taskType'] ?? 'approval';

            if (in_array($taskType, ['approval', 'form', 'review'], true)) {
                $humanBranches[] = [$nextNodeId, $nextNode];
            } else {
                $autoBranches[] = [$nextNodeId, $nextNode];
            }
        }

        foreach ($humanBranches as [$nextNodeId, $nextNode]) {
            foreach ($this->taskManager->createTasks($instance, $nextNodeId, $nextNode['data'] ?? []) as $task) {
                unset($task);
            }
        }

        if (!empty($humanBranches)) {
            $lastHumanNodeId = $humanBranches[array_key_last($humanBranches)][0];
            $instance->update([
                'status'          => 'waiting',
                'current_node_id' => $lastHumanNodeId,
            ]);
        }

        // Otomatik dallanmaları işlet
        foreach ($autoBranches as [$nextNodeId, $nextNode]) {
            $instance->update(['current_node_id' => $nextNodeId]);
            $taskType = $nextNode['data']['taskType'] ?? 'approval';

            if ($taskType === 'notify') {
                $nodeData = $nextNode['data'] ?? [];
                $notifyTo = $nodeData['notifyTo'] ?? 'custom';
                $email = null;
                $targetUser = null;

                if ($notifyTo === 'initiator') {
                    $targetUser = $instance->starter;
                    $email = $targetUser?->email;
                } elseif ($notifyTo === 'department_manager') {
                    $starter = $instance->starter;
                    $targetUser = $starter?->manager ?? $starter?->department?->manager;
                    $email = $targetUser?->email;
                } elseif ($notifyTo === 'custom' || empty($notifyTo)) {
                    $email = $nodeData['notifyEmail'] ?? null;
                }

                $subject = $nodeData['notifySubject'] ?? 'Süreç Bilgilendirmesi';
                $messageBody = $nodeData['notifyMessage'] ?? $nodeData['description'] ?? "Sürecinizle (#{$instance->id}) ilgili sistem bildirimi.";

                if ($targetUser) {
                    UserNotification::create([
                        'user_id' => $targetUser->id,
                        'type'    => 'system',
                        'title'   => $subject,
                        'body'    => $messageBody,
                        'data'    => [
                            'url'                 => route('processes.tracker', $instance->id),
                            'action_url'          => route('processes.tracker', $instance->id),
                            'process_instance_id' => $instance->id,
                        ],
                    ]);
                }

                if (!empty($email)) {
                    try {
                        Mail::to($email)
                            ->queue(new ProcessNotificationMail($instance, array_merge($nodeData, [
                                'notifySubject' => $subject,
                                'description'   => $messageBody,
                            ])));
                    } catch (\Throwable $e) {
                        Log::error("E-posta gönderim hatası (Notify Node): " . $e->getMessage());
                    }
                }
                $this->advance($instance, 'approve');
            } elseif ($taskType === 'document_gen') {
                try {
                    $certGen = app(\App\Services\CertificateGenerator::class);
                    $pdf = $certGen->generate($instance);
                    $docTitle = $nextNode['data']['documentTitle'] ?? 'Analiz Sertifikası (CoA)';
                    
                    $data = (array) $instance->data;
                    $docs = $data['_generated_documents'] ?? [];
                    $docs[] = [
                        'title'      => $docTitle,
                        'type'       => $nextNode['data']['documentType'] ?? 'certificate_of_analysis',
                        'url'        => route('processes.certificate', $instance->id),
                        'created_at' => now()->toIso8601String(),
                    ];
                    $data['_generated_documents'] = $docs;
                    $instance->update(['data' => $data]);
                } catch (\Throwable $e) {
                    Log::error("Otomatik Belge Üretici Hatası: " . $e->getMessage());
                }
                $this->advance($instance, 'approve');
            } elseif ($taskType === 'sap_sync') {
                try {
                    $sapService = app(\App\Services\SapIntegrationService::class);
                    $followUp = $instance->latestFollowUp;
                    if ($followUp) {
                        $sapService->syncSampleOrder($followUp);
                    }
                } catch (\Throwable $e) {
                    Log::error("SAP Senkronizasyon Düğüm Hatası: " . $e->getMessage());
                }
                $this->advance($instance, 'approve');
            } elseif ($taskType === 'follow_up') {
                try {
                    $nodeData = $nextNode['data'] ?? [];
                    $assignedTo = $instance->started_by;
                    $assignType = $nodeData['assignType'] ?? 'starter';
                    if ($assignType === 'user' && !empty($nodeData['assignValue'])) {
                        $assignedTo = (int) $nodeData['assignValue'];
                    }

                    $options = [
                        'followUpTitle'     => $nodeData['followUpTitle'] ?? $nodeData['label'] ?? 'Süreç Takibi',
                        'followUpPrompt'    => $nodeData['followUpPrompt'] ?? 'Talep süreci tamamlanmıştır. Lütfen güncel durumu sisteme işleyiniz.',
                        'subFormId'         => $nodeData['subFormId'] ?? null,
                        'followUpDays'      => $nodeData['followUpDays'] ?? null,
                        'followUpInterval'  => $nodeData['followUpIntervalDays'] ?? null,
                        'maxReminders'      => $nodeData['followUpMaxReminders'] ?? null,
                        'followUpType'      => $nodeData['followUpType'] ?? 'general_follow_up',
                        'assigned_to'       => $assignedTo,
                    ];
                    app(\App\Services\FollowUpService::class)->scheduleFollowUp($instance, $options);
                } catch (\Throwable $e) {
                    Log::error("Follow-up Düğüm Hatası: " . $e->getMessage());
                }
                $this->advance($instance, 'approve');
            } elseif ($taskType === 'system_rule') {
                $this->advance($instance, 'approve');
            } else {
                $this->advance($instance, 'approve');
            }
        }
    }

    /**
     * Sorumluluk: Hiyerarşi motorundan aktör ve hedef verilerini çekerek kural motorunun 
     * anlayacağı genişletilmiş bağlam (Context) matrisini inşa eder.
     */
    private function buildExtendedContext(ProcessInstance $instance): array
    {
        // 1. Süreci işleten/başlatan personelin hiyerarşi motorundaki (Node) kaydını ve dinamik şema metadatasını bul
        $actorNode = HierarchyNode::where('user_id', $instance->started_by)->first();

        // 2. Form verileri içerisinden seçilen bir makine veya araç varsa hiyerarşiden onun metadatasını yükle
        $targetNode = null;
        if (!empty($instance->data['selected_equipment_node_id'])) {
            $targetNode = HierarchyNode::find($instance->data['selected_equipment_node_id']);
        }

        return [
            'form'   => $instance->data ?? [],
            'actor'  => $actorNode ? $actorNode->toArray() : ['metadata' => []],
            'target' => $targetNode ? $targetNode->toArray() : ['metadata' => []]
        ];
    }

    /**
     * GÖREV 3 Örnek Yardımcı Metot: Araç talep senaryolarında, personelin dinamik şemasında 
     * 'src_belgesi' false ise veya geçerli ehliyeti yoksa süreci otomatik güvenliğe düşürür.
     */
    private function shouldApplyAutomatedSafetyOverride(array $context): bool
    {
        $isVehicleRequest = ($context['form']['talep_tipi'] ?? '') === 'Araç';
        $hasSrcCertificate = $context['actor']['metadata']['src_belgesi'] ?? true;

        // Araç talep edilmesine rağmen aktörün dinamik şemasında SRC belgesi yok (false) ise override tetiklenir
        if ($isVehicleRequest && !$hasSrcCertificate) {
            Log::warning("Kural Motoru Güvenlik İhlali Yakaladı: Personelin SRC belgesi bulunmuyor. Süreç saptırılıyor.");
            return true;
        }

        return false;
    }

    /**
     * GÖREV 3 Örnek Yardımcı Metot: Güvenlik kriterlerini karşılamayan personeli 
     * otomatik olarak doğrudan "Otomatik Ret" veya "İK İnceleme" düğümüne yönlendirecek sahte aksiyon üretir.
     */
    private function generateAutomatedSafetyRejectAction(): RuleAction
    {
        return new RuleAction([
            'type'   => 'route_to',
            'params' => [
                'target_node_id' => 'safety_reject_node_id', // Akış şemasındaki otomatik ret veya revizyon adımı
                'reason'         => 'Dinamik şema kontrolü başarısız: Aktörün SRC belgesi veya ehliyeti yetersiz.'
            ]
        ]);
    }

    private function findStartNode(array $nodes): ?array
    {
        return collect($nodes)->first(function ($node) {
            $type = $node['type'] ?? null;
            $taskType = $node['data']['taskType'] ?? null;
            return in_array($type, ['input', 'start'], true) || $taskType === 'start';
        }) ?? collect($nodes)->first();
    }

    private function getNodeById(array $nodes, string $id): ?array
    {
        return collect($nodes)->firstWhere('id', $id);
    }

    private function normalizeEdgeAction(string $action): string
    {
        return match ($action) {
            'approve' => 'approved',
            'reject'  => 'rejected',
            'revise'  => 'revised',
            default   => $action,
        };
    }

    private function supportsActionBranching(array $node): bool
    {
        $taskType = $node['data']['taskType'] ?? '';
        if ($taskType === 'approval') {
            return true;
        }
        return in_array($taskType, ['review', 'form'], true) && !empty($node['data']['rejectEnabled']);
    }

    private function normalizedGraph(Workflow $workflow): array
    {
        return app(WorkflowGraphNormalizer::class)->normalize(
            $workflow->nodes ?? [],
            $workflow->edges ?? []
        );
    }

    private function handleProcessCompleted(ProcessInstance $instance): void
    {
        try {
            $workflow = $instance->workflow;
            if (!$workflow) {
                return;
            }

            $workflowName = mb_strtolower($workflow->name ?? '', 'UTF-8');
            $isSample = !empty($workflow->follow_up_enabled)
                || !empty($workflow->is_sample_workflow)
                || str_contains($workflowName, 'numune')
                || str_contains($workflowName, 'sample');

            if ($isSample) {
                app(\App\Services\FollowUpService::class)->scheduleFollowUp($instance);
            }
        } catch (\Throwable $e) {
            Log::error("Süreç tamamlama hook hatası (FollowUp): " . $e->getMessage());
        }
    }
}
