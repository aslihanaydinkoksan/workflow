<?php

namespace App\Services;

use App\Models\ProcessInstance;
use App\Models\Workflow;
use App\Models\Node as HierarchyNode;
use Illuminate\Support\Facades\Log;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Mail;
use App\Mail\ProcessNotificationMail;

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
                // Bildirim Paneli (Çan İkonu) İçin Veri
                UserNotification::create([
                    'user_id' => $instance->started_by,
                    'type'    => 'system',
                    'title'   => 'Süreç Otomatik Yönlendirildi / Reddedildi',
                    'body'    => $reason, // Direkt kural motorundan gelen spesifik sebebi (Örn: SRC Yok) basıyoruz
                    'message' => "Başlatmış olduğunuz #{$instance->id} numaralı süreç, sistem kuralları gereği şu karara istinaden yönlendirilmiştir:\n\n{$reason}",
                    'link'    => route('processes.tracker', $instance->id),
                    'data'    => json_encode(['url' => route('processes.tracker', $instance->id)]),
                    'is_read' => false,
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

            // --- EVRENSEL RET YAKALAYICI (TÜRKÇE KARAKTER BUG'I İÇİN KESİN ÇÖZÜM) ---
            $customName = $nextNode['data']['customName'] ?? '';
            $label = $nextNode['data']['label'] ?? '';
            $fullNodeName = mb_strtolower($customName . ' ' . $label, 'UTF-8');
            // Türkçe karakterleri tamamen İngilizceye çevirerek hatayı yok ediyoruz
            $fullNodeName = str_replace(['ı', 'i̇', 'ğ', 'ü', 'ş', 'ö', 'ç'], ['i', 'i', 'g', 'u', 's', 'o', 'c'], $fullNodeName);

            if (str_contains($fullNodeName, 'iptal') || str_contains($fullNodeName, 'red') || str_contains($fullNodeName, 'ret') || $action === 'rejected' || ($ruleAction->type ?? '') === 'reject_and_route') {
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
                $email = $nextNode['data']['notifyEmail'] ?? null;
                if (!empty($email)) {
                    Mail::to($email)
                        ->queue(new ProcessNotificationMail($instance, $nextNode['data'] ?? []));
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
}
