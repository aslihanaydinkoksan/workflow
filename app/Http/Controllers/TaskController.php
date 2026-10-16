<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\FormSubmission;
use App\Models\FormTemplate;
use App\Services\NotificationService;
use App\Services\ProcessEngine;
use App\Services\TaskManager;
use App\Services\TaskVisibility;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TaskFormExport;
use Illuminate\Support\Str;

class TaskController extends Controller
{
    public function index()
    {
        /** @var User $user */
        $user = Auth::user();

        $baseQuery = TaskVisibility::queryForUser($user)
            ->with(['processInstance.workflow', 'processInstance.starter']);

        // Bekleyen Görevler
        $tasks = (clone $baseQuery)->where('status', 'pending')->latest()->get();

        // Geçmiş (Tamamlanmış vb.) Görevler
        $completedTasks = (clone $baseQuery)->where('status', '!=', 'pending')->latest()->get();

        // Aktif kullanıcı listesi (Vekil atamak için)
        $users = User::where('is_active', true)
            ->where('id', '!=', $user->id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        // Verdiğim Vekaletler
        $givenDelegations = \App\Models\Delegation::where('delegator_id', $user->id)
            ->with('delegatee:id,name')
            ->orderByDesc('created_at')
            ->get();

        // Aldığım Vekaletler
        $receivedDelegations = \App\Models\Delegation::where('delegatee_id', $user->id)
            ->with('delegator:id,name')
            ->orderByDesc('created_at')
            ->get();

        // Aktif / Bekleyen Numune ve Süreç Takipleri (Follow-Ups)
        $pendingFollowUps = \App\Models\FollowUp::where('status', 'pending')
            ->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhereHas('processInstance', fn($sub) => $sub->where('started_by', $user->id));
            })
            ->orderBy('scheduled_at')
            ->with(['processInstance.workflow', 'processInstance.starter'])
            ->get();

        $activeWorkflows = \App\Models\Workflow::where('status', 'active')
            ->select('id', 'name', 'category')
            ->orderBy('name')
            ->get();

        $allCategories = [];
        foreach ($activeWorkflows as $wf) {
            $cats = is_array($wf->category) ? $wf->category : [$wf->category];
            foreach ($cats as $c) {
                if (!empty($c) && !in_array($c, $allCategories, true)) {
                    $allCategories[] = $c;
                }
            }
        }

        return Inertia::render('Tasks/Index', [
            'tasks'                => $tasks,
            'completedTasks'       => $completedTasks,
            'users'                => $users, // Yeni Prop
            'all_users'            => User::where('is_active', true)->select('id', 'name')->orderBy('name')->get(),
            'given_delegations'    => $givenDelegations, // Yeni Prop
            'received_delegations' => $receivedDelegations, // Yeni Prop
            'pending_follow_ups'   => $pendingFollowUps,
            'active_workflows'     => $activeWorkflows,
            'workflow_categories'  => $allCategories,
        ]);

    }

    public function show(Task $task)
    {
        /** @var User $user */
        $user = Auth::user();
        $isAdmin = $user->hasRole('Admin') || $user->hasRole('superadmin');

        // YENİ: Kullanıcı bu görevi şu an yapabiliyor mu VEYA geçmişte o mu tamamladı?
        $isAssigned = $isAdmin
            || TaskVisibility::userCanAccessTask($user, $task)
            || ($task->status !== 'pending' && $task->completed_by === $user->id); // Bu satırı ekledik!

        if (!$isAssigned) {
            abort(403, 'Bu görevi görüntüleme yetkiniz bulunmuyor.'); // 403 fırlatırken özel mesaj eklendi
        }

        $task->load('processInstance.workflow.formTemplate', 'processInstance.starter');

        // Form Görevi ise ilgili alt formu (Sub-Form) getir
        $workflowNode = collect($task->processInstance->workflow->nodes ?? [])->firstWhere('id', $task->node_id);
        $subFormId = $workflowNode['data']['subFormId'] ?? null;

        /** @var FormTemplate|null $subForm */
        $subForm = $subFormId ? FormTemplate::find($subFormId) : null;

        $isStartNode = in_array((string) $task->node_id, ['1', 'node_start'], true)
            || in_array($workflowNode['type'] ?? '', ['start', 'input'], true);

        // Eğer başlangıç formu revize ediliyorsa ve özel bir alt form tanımlanmamışsa, ana başlangıç formunu yükle
        if (! $subForm && ($isStartNode || $task->is_revision)) {
            $subForm = $task->processInstance->workflow->formTemplate;
        }

        $previousForms = $this->getPreviousFormsForTask($task);
        $instanceData = (array) ($task->processInstance->data ?? []);

        /** @var FormTemplate|null $workflowStartForm */
        $workflowStartForm = $task->processInstance->workflow->formTemplate;

        // Kullanıcı şu an başlangıç formunu düzenliyorsa/revize ediyorsa, aynı formu yukarıda pasif salt-okunur olarak çift gösterme
        if (! $task->is_revision && $workflowStartForm && ! empty($instanceData)) {
            $startFormEntry = $this->buildFormEntryFromData(
                $workflowStartForm,
                $instanceData,
                'Süreç Başlangıç Formu',
                $task->processInstance->starter?->name
            );

            if ($startFormEntry && ! $this->formEntryExists($previousForms, $startFormEntry)) {
                array_unshift($previousForms, $startFormEntry);
            }
        }

        $prefilledFormData = [];
        if ($task->type === 'form' && $task->status === 'pending' && $subForm) {
            $prefilledFormData = $this->extractFormDataForTemplate($instanceData, $subForm);
        }


        $previousTaskNotes = $this->getPreviousTaskNotesForTask($task);

        $documents = $this->extractDocumentsForTask($task);
        $leadTimeAnalytics = app(\App\Services\LeadTimeService::class)->analyzeProcess($task->processInstance);
        $budgetControl = app(\App\Services\BudgetControlService::class)->evaluateBudget($task->processInstance);

        return Inertia::render('Tasks/Show', [
            'task' => $task,
            'nodeData' => $workflowNode,
            'subForm' => $subForm,
            'initialData' => $instanceData,
            'previousForms' => $previousForms,
            'previousTaskNotes' => $previousTaskNotes,
            'prefilledFormData' => $prefilledFormData,
            'fieldAudits' => app(\App\Services\ProcessAuditService::class)->getAuditsForInstance($task->processInstance),
            'appLogo' => \App\Models\Setting::where('key', 'app_logo')->value('value'),
            'documents' => $documents,
            'leadTimeAnalytics' => $leadTimeAnalytics,
            'budgetControl' => $budgetControl,
        ]);
    }

    /**
     * Çoklu Seçim & Toplu Karar Merkezi (Bulk Approve / Reject - SAP MyInbox Standardı)
     */
    public function bulkAction(Request $request, ProcessEngine $engine, TaskManager $manager)
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'task_ids' => 'required|array|min:1',
            'task_ids.*' => 'required|integer',
            'action' => 'required|string|in:approve,reject',
            'comment' => 'nullable|string|max:1000',
        ]);

        $taskIds = $validated['task_ids'];
        $action = $validated['action'];
        $comment = $validated['comment'] ?: ($action === 'approve' ? 'Toplu Onaylandı (Hızlı Karar Merkezi)' : 'Toplu Reddedildi');

        $successCount = 0;
        $skippedCount = 0;
        $isAdmin = $user->hasRole('Admin') || $user->hasRole('superadmin');

        foreach ($taskIds as $taskId) {
            $task = Task::with('processInstance.workflow')->find($taskId);
            if (!$task || $task->status !== 'pending' || $task->type !== 'approval') {
                $skippedCount++;
                continue;
            }

            if (!TaskVisibility::userCanAccessTask($user, $task)) {
                $skippedCount++;
                continue;
            }

            // 4-Göz SoD Koruması: Talep sahibi kendi onayını veremez
            if ((int)$task->processInstance->started_by === $user->id && !$isAdmin) {
                $skippedCount++;
                continue;
            }

            try {
                if ($action === 'approve') {
                    $manager->completeTask($task, $comment, $user->id);
                    $workflowNode = collect($task->processInstance->workflow->nodes ?? [])->firstWhere('id', $task->node_id);
                    $approvalPolicy = $workflowNode['data']['approvalPolicy'] ?? 'any';
                    
                    $shouldAdvance = true;
                    if ($approvalPolicy === 'all') {
                        $hasPending = $manager->nodeHasPendingTasks($task->processInstance, $task->node_id);
                        $shouldAdvance = !$hasPending;
                    } else {
                        $manager->cancelPendingTasksForNode($task->processInstance, $task->node_id, $task->id);
                    }

                    if ($shouldAdvance) {
                        $instance = $task->processInstance->fresh();
                        $instance->update(['current_node_id' => $task->node_id, 'status' => 'running']);
                        $engine->onTaskCompleted($instance, 'approved', $comment, $task->node_id);
                    }
                } else {
                    $manager->rejectTask($task, $comment, $user->id);
                    $manager->cancelPendingTasksForNode($task->processInstance, $task->node_id, $task->id);
                    $instance = $task->processInstance->fresh();
                    $instance->update(['current_node_id' => $task->node_id, 'status' => 'running']);
                    $engine->onTaskCompleted($instance, 'rejected', $comment, $task->node_id);
                }

                // Denetim İzi Kaydı
                app(\App\Services\ProcessAuditService::class)->recordDiff(
                    $task->processInstance,
                    ['bulk_decision' => null],
                    ['bulk_decision' => $action],
                    $user->id,
                    'bulk_' . $action,
                    $task->id,
                    $comment
                );

                $successCount++;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("Bulk action failed for task #{$taskId}: " . $e->getMessage());
                $skippedCount++;
            }
        }

        $actionWord = $action === 'approve' ? 'onaylandı' : 'reddedildi';
        $msg = "{$successCount} adet onay görevi başarıyla toplu {$actionWord}.";
        if ($skippedCount > 0) {
            $msg .= " ({$skippedCount} görev yetki veya kural kısıtı nedeniyle atlandı.)";
        }

        return back()->with('success', $msg);
    }

    /**
     * Görev veya süreçle ilgili fatura, ek, resim ve resmi PDF belgelerini ayıklar.
     */
    private function extractDocumentsForTask(Task $task): array
    {
        $docs = [];
        $instance = $task->processInstance;
        $workflowName = mb_strtolower($instance->workflow->name ?? '', 'UTF-8');
        $instanceData = (array) ($instance->data ?? []);

        // 1. Resmi Dış Görev & Seyahat İzin Belgesi PDF
        if (str_contains($workflowName, 'dış görev') || str_contains($workflowName, 'seyahat') || str_contains($workflowName, 'harcırah')) {
            $docs[] = [
                'id' => 'travel_order_pdf',
                'title' => 'Resmi Dış Görevlendirme ve Seyahat İzin Belgesi',
                'badge' => 'Köksan Onaylı İzin Belgesi (PDF)',
                'type' => 'pdf',
                'url' => route('processes.travel-order', $instance->id),
                'icon' => '📄',
                'is_primary' => true,
            ];
        }

        // 2. Numune Analiz Sertifikası (CoA PDF)
        if (str_contains($workflowName, 'numune') || str_contains($workflowName, 'levha') || str_contains($workflowName, 'coa')) {
            $docs[] = [
                'id' => 'coa_certificate_pdf',
                'title' => 'Teknik Analiz Sertifikası (CoA)',
                'badge' => 'Laboratuvar Kalite Belgesi (PDF)',
                'type' => 'pdf',
                'url' => route('processes.certificate', $instance->id),
                'icon' => '🔬',
                'is_primary' => empty($docs),
            ];
        }

        // 3. Makine Arıza & Bakım Onarım İş Emri Fişi (TPM PDF)
        if (str_contains($workflowName, 'bakım') || str_contains($workflowName, 'tpm') || str_contains($workflowName, 'arıza')) {
            $docs[] = [
                'id' => 'maintenance_order_pdf',
                'title' => 'Makine Bakım Onarım İş Emri Fişi',
                'badge' => 'TPM & Bakım Belgesi (PDF)',
                'type' => 'pdf',
                'url' => route('processes.maintenance-order', $instance->id),
                'icon' => '🛠️',
                'is_primary' => empty($docs),
            ];
        }

        // 4. DÖF / 8D Kalite Raporu PDF
        if (str_contains($workflowName, 'döf') || str_contains($workflowName, 'dof') || str_contains($workflowName, 'kalite') || str_contains($workflowName, 'capa') || str_contains($workflowName, '8d')) {
            $docs[] = [
                'id' => 'dof_report_pdf',
                'title' => 'Resmi DÖF / 8D Kalite Raporu',
                'badge' => 'ISO 9001 Kalite Güvence (PDF)',
                'type' => 'pdf',
                'url' => route('processes.dof-report', $instance->id),
                'icon' => '🔬',
                'is_primary' => empty($docs),
            ];
        }

        // 5. Malzeme Satın Alma Talep Fişi PDF
        if (str_contains($workflowName, 'satın alma') || str_contains($workflowName, 'malzeme')) {
            $docs[] = [
                'id' => 'purchase_order_pdf',
                'title' => 'Malzeme Satın Alma Talep Fişi',
                'badge' => 'Kurumsal Satın Alma Onayı (PDF)',
                'type' => 'pdf',
                'url' => route('processes.purchase-order', $instance->id),
                'icon' => '🛒',
                'is_primary' => empty($docs),
            ];
        }

        // 6. Form Verisi İçindeki Ek Belgeler / Faturalar
        $fileKeys = ['fatura', 'bilet', 'otel', 'dekont', 'ek_dosya', 'dosya', 'file', 'attachment', 'belge', 'makbuz'];
        foreach ($instanceData as $key => $val) {
            if (empty($val)) continue;

            $isMatch = false;
            foreach ($fileKeys as $fk) {
                if (str_contains($key, $fk)) {
                    $isMatch = true;
                    break;
                }
            }

            if ($isMatch || (is_string($val) && preg_match('/\.(pdf|jpg|jpeg|png|webp|xlsx|xls)$/i', $val))) {
                $ext = pathinfo((string)$val, PATHINFO_EXTENSION);
                $isPdf = strtolower($ext) === 'pdf';
                $isImg = in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'webp'], true);
                
                $title = ucwords(str_replace('_', ' ', $key));
                $fileUrl = str_starts_with((string)$val, 'http') ? $val : (str_starts_with((string)$val, '/storage') ? $val : '/storage/' . ltrim((string)$val, '/'));

                $docs[] = [
                    'id' => 'uploaded_' . $key,
                    'title' => $title,
                    'badge' => strtoupper($ext ?: 'DOSYA') . ' Eki',
                    'type' => $isPdf ? 'pdf' : ($isImg ? 'image' : 'file'),
                    'url' => $fileUrl,
                    'icon' => $isPdf ? '📑' : ($isImg ? '🖼️' : '📎'),
                    'is_primary' => empty($docs),
                ];
            }
        }

        // 4. Görev Rapor Form Dökümü (Her halükarda hazır resmi döküm)
        $docs[] = [
            'id' => 'task_summary_pdf',
            'title' => 'Görev Form Resmi Dökümü #' . $task->id,
            'badge' => 'Canlı Form Çıktısı (PDF)',
            'type' => 'pdf',
            'url' => route('tasks.export.pdf', $task->id),
            'icon' => '📋',
            'is_primary' => empty($docs),
        ];

        return $docs;
    }

    public function update(Request $request, Task $task, ProcessEngine $engine, TaskManager $manager)
    {
        /** @var User $user */
        $user = Auth::user();
        $isAssigned = TaskVisibility::userCanAccessTask($user, $task);

        if (!$isAssigned) {
            abort(403);
        }

        if ($task->status !== 'pending') {
            return redirect()->route('processes.tracker', $task->process_instance_id)
                ->with('info', 'Bu görev başka bir yetkili tarafından zaten tamamlanmış veya işleme kapatılmıştır.');
        }

        $action = $request->input('task_action', $request->input('action'));
        $comment = $request->input('comment');
        $formData = $request->input('answers', $request->input('data'));

        // Karar ve form verisi senkronizasyonu
        if (is_array($formData)) {
            if ($action === 'reject') {
                $formData['isletme_karari'] = 'Reddedildi';
            } elseif ($action === 'revise') {
                $formData['isletme_karari'] = 'Revizyon İstendi';
            } elseif ($task->type === 'approval' && $action === 'approve') {
                if (($formData['isletme_karari'] ?? '') === 'Reddedildi' || empty($formData['isletme_karari'])) {
                    $formData['isletme_karari'] = 'Kabul Edildi';
                }
            } elseif ($task->is_revision && $action === 'approve') {
                $formData['isletme_karari'] = 'Revize Edildi (İnceleniyor)';
            }
        }

        // Sub-form verisi geldiyse ProcessInstance içine kaydet
        if (is_array($formData) && ($task->type === 'form' || ! empty($formData))) {
            $instance = $task->processInstance;
            $oldData = (array) ($instance->data ?? []);
            $mergedData = array_merge($oldData, $formData);
            $instance->update(['data' => $mergedData]);

            // Denetim İzi (Audit Diff): Değişen veya yeni eklenen alanları logla
            app(\App\Services\ProcessAuditService::class)->recordDiff(
                $instance,
                $oldData,
                $formData,
                Auth::id(),
                $task->is_revision ? 'revision' : 'form_submit',
                $task->id,
                $comment
            );

            FormSubmission::updateOrCreate(
                [
                    'process_instance_id' => $instance->id,
                    'task_id' => $task->id,
                ],
                [
                    'data' => $formData,
                    'submitted_by' => Auth::id(),
                ]
            );
        }

        // KOŞULLU DOĞRULAMA (Sadece Onaylama İşleminde Kontrol Edilir)
        if (!in_array($action, ['reject', 'revise'], true)) {
            // 0. GÖREVLER AYRILIĞI & 4-GÖZ İLKESİ (Separation of Duties - SoD) KORUMASI:
            // Talep sahibi kendi onay görevini onaylayamaz!
            $isAdminUser = Auth::user()->hasRole('Admin') || Auth::user()->hasRole('superadmin');
            if ($task->type === 'approval' && (int)$task->processInstance->started_by === Auth::id() && !$isAdminUser) {
                return back()->with('error', 'Görevler Ayrılığı (4-Göz İlkesi / Separation of Duties) gereğince kendi başlattığınız talebi onaylayamazsınız. Onay bir üst amirinize aittir.');
            }

            $workflowNode = collect($task->processInstance->workflow->nodes ?? [])->firstWhere('id', $task->node_id);
            $allData = array_merge((array) $task->processInstance->data, (array) ($formData ?? []));

            // 1. Akış Tasarımcısı (Designer) Koşullu Alan Doğrulaması (Generic: termin, fatura_no, dosya_no vb.)
            if (!empty($workflowNode['data']['requireFieldOnApprove'])) {
                $requiredKey = trim($workflowNode['data']['requiredFieldName'] ?? '');
                if ($requiredKey !== '') {
                    $val = $allData[$requiredKey] ?? null;
                    if ($val === null || $val === '') {
                        $errMsg = !empty($workflowNode['data']['requiredFieldErrorMessage'])
                            ? $workflowNode['data']['requiredFieldErrorMessage']
                            : "Bu adımı onaylayabilmek için '{$requiredKey}' alanının doldurulması zorunludur.";
                        return back()->with('error', $errMsg);
                    }
                }
            }

            // 2. Geriye Dönük Uyumluluk (Numune Kabul / Termin Bildirimi)
            $nodeRequiresTermin = !empty($workflowNode['data']['requireTermin'])
                || !empty($workflowNode['data']['require_termin']);

            $nodeLabel = mb_strtolower(($workflowNode['data']['customName'] ?? '') . ' ' . ($workflowNode['data']['label'] ?? ''), 'UTF-8');
            $isAcceptanceNode = str_contains($nodeLabel, 'kabul') || str_contains($nodeLabel, 'isletme') || str_contains($nodeLabel, 'termin');

            $hasTerminField = array_key_exists('termin_tarihi', $allData)
                || array_key_exists('termin_araligi', $allData)
                || array_key_exists('termin', $allData);

            $terminValue = $allData['termin_tarihi'] ?? $allData['termin_araligi'] ?? $allData['termin'] ?? null;

            if (($nodeRequiresTermin || ($isAcceptanceNode && $hasTerminField)) && empty($terminValue)) {
                return back()->with('error', 'Numuneyi kabul ederken bir termin tarihi / aralığı bildirmek zorunludur.');
            }
        }

        // Ret ve Revizyon İşlemlerinde Açıklama / Gerekçe Mecburidir
        if (in_array($action, ['reject', 'revise'], true)) {
            $reason = trim((string) ($comment ?: ''));
            if ($reason === '') {
                $actionName = $action === 'reject' ? 'Ret' : 'Revizyon';
                return back()->with('error', "{$actionName} işlemini gerçekleştirebilmek için lütfen bir gerekçe / açıklama belirtiniz.");
            }
            $comment = $reason;
            if (is_array($formData)) {
                $formData['isletme_notu'] = $reason;
            }
        }

        if ($action === 'reject') {
            $manager->rejectTask($task, $comment, $user->id);
            $manager->cancelPendingTasksForNode($task->processInstance, $task->node_id, $task->id);
            app(NotificationService::class)->taskRejected(
                $task->processInstance,
                $task,
                $user,
                $comment
            );
            $edgeAction = 'rejected';
            $shouldAdvance = true;
        } elseif ($action === 'revise') {
            $manager->reviseTask($task, $comment, $user->id);
            $manager->cancelPendingTasksForNode($task->processInstance, $task->node_id, $task->id);
            $edgeAction = 'revised';
            $shouldAdvance = true;
        } else {
            $manager->completeTask($task, $comment, $user->id);
            $edgeAction = 'approved';

            $workflowNode = collect($task->processInstance->workflow->nodes ?? [])->firstWhere('id', $task->node_id);
            $approvalPolicy = $workflowNode['data']['approvalPolicy'] ?? 'any';

            if ($approvalPolicy === 'all') {
                // Konsensüs / Tümünün Onayı Şart:
                // Bu adımda hala bekleyen (pending) başka bir görev var mı?
                $hasPending = $manager->nodeHasPendingTasks($task->processInstance, $task->node_id);
                if ($hasPending) {
                    $shouldAdvance = false;
                } else {
                    $shouldAdvance = true;
                }
            } else {
                // İlk Onaylayan Yeterli (any / varsayılan):
                // Diğer bekleyen eş görevleri iptal et ve sıradaki adıma geç
                $manager->cancelPendingTasksForNode($task->processInstance, $task->node_id, $task->id);
                $shouldAdvance = true;
            }
        }

        if ($shouldAdvance) {
            $instance = $task->processInstance->fresh();
            $instance->update([
                'current_node_id' => $task->node_id,
                'status' => 'running',
            ]);
            $engine->onTaskCompleted($instance, $edgeAction, $comment, $task->node_id);
            $instance = $instance->fresh();
            if ($instance->status === 'completed') {
                $starter = $instance->starter; // Süreci başlatan kullanıcı

                if ($starter) {
                    $workflowName = $instance->workflow->name ?? 'İş Akışı';
                    $isSample = !empty($instance->workflow->follow_up_enabled)
                        || !empty($instance->workflow->is_sample_workflow)
                        || str_contains(mb_strtolower($workflowName, 'UTF-8'), 'numune')
                        || str_contains(mb_strtolower($workflowName, 'UTF-8'), 'sample');

                    if ($isSample) {
                        app(\App\Services\FollowUpService::class)->scheduleFollowUp($instance);
                    }

                    // 1. Veritabanı Bildirimi (user_notifications tablosu için)
                    $bodyText = $isSample
                        ? "Harika haber! #{$instance->id} numaralı numune talebiniz ve analiz süreçleri tamamlanmıştır. Analiz sertifikanızı indirebilirsiniz."
                        : "Harika haber! #{$instance->id} numaralı {$workflowName} talebiniz tüm onaylardan geçmiş ve başarıyla tamamlanmıştır.";

                    UserNotification::create([
                        'user_id' => $starter->id,
                        'type'    => 'workflow_completed',
                        'title'   => 'Süreç Başarıyla Tamamlandı',
                        'body'    => $bodyText,
                        'task_id' => $task->id,
                        'read_at' => null,
                    ]);
                    // 2. E-Posta Gönderimi
                    if (!empty($starter->email)) {
                        Mail::raw(
                            $bodyText,
                            function ($message) use ($starter, $instance) {
                                $message->to($starter->email)
                                    ->subject("Talep Süreciniz Tamamlandı (#{$instance->id})");
                            }
                        );
                    }
                }
            }
            $message = match ($action) {
                'reject' => 'Görev reddedildi. Süreç, tasarımdaki Red bağlantısına göre yönlendirildi.',
                'revise' => 'Revizyon talebiniz iletildi. Süreç, tasarımdaki Revize / Geri Dön bağlantısına göre yönlendirildi.',
                default  => $task->is_revision
                    ? 'Talebiniz başarıyla revize edildi ve onay makamına yeniden iletildi.'
                    : 'Görev başarıyla onaylandı. Süreç otomatik olarak sıradaki adıma geçti.',
            };
        } else {
            $task->processInstance->update(['status' => 'waiting']);
            $actionLabel = $task->type === 'form' ? 'Form yanıtınız' : 'Onayınız';
            $message = "{$actionLabel} başarıyla kaydedildi. Çoklu onay (konsensüs) kuralı gereği diğer yetkili(ler)in işlemi bekleniyor.";
        }

        return redirect()->route('processes.tracker', $task->process_instance_id)->with('success', $message);
    }

    public function undo(Task $task)
    {
        /** @var User $user */
        $user = Auth::user();
        $isAdmin = $user->hasRole('Admin') || $user->hasRole('superadmin');

        $isAssigned = $isAdmin || TaskVisibility::userCanAccessTask($user, $task);

        if (!$isAssigned || $task->status === 'pending') {
            abort(403);
        }

        $instance = $task->processInstance;

        if (!$isAdmin) {
            // Kontrol: Eğer süreç zaten tamamlanmışsa geri alınamaz
            if ($instance->status === 'completed') {
                return back()->with('error', 'Süreç tamamen sonlandığı için bu işlem geri alınamaz.');
            }

            // Kontrol: Bir sonraki adımlardaki görevler BAŞKA BİRİ tarafından tamamlanmış mı?
            $newerCompletedTasks = Task::where('process_instance_id', $instance->id)
                ->where('id', '>', $task->id)
                ->where('status', '!=', 'pending')
                ->exists();

            if ($newerCompletedTasks) {
                return back()->with('error', 'Sizden sonraki kişi işlem yaptığı için geri alma başarısız oldu.');
            }
        }

        if ($isAdmin) {
            // Eğer admin geri alıyorsa, id'si bu task'tan büyük olan TÜM (bekleyen ve tamamlanmış) görevleri sil ki süreç temizlensin
            Task::where('process_instance_id', $instance->id)
                ->where('id', '>', $task->id)
                ->delete();
        } else {
            // Aktif (pending) olan tüm ileriye dönük görevleri sil
            Task::where('process_instance_id', $instance->id)
                ->where('status', 'pending')
                ->delete();
        }

        // Bu görevi tekrar bekliyor (pending) durumuna al
        $task->update([
            'status' => 'pending',
            'completed_at' => null,
            'comment' => null
        ]);

        // Süreci bu düğüme geri çek
        $instance->update([
            'status' => 'waiting',
            'current_node_id' => $task->node_id
        ]);

        return redirect()->route('tasks.show', $task->id)->with('success', 'İşlem başarıyla geri alındı.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getPreviousFormsForTask(Task $task): array
    {
        $workflow = $task->processInstance->workflow;

        // P1131 HATASININ ÇÖZÜMÜ: Buradaki PHPDoc satırını sildik. 
        // IDE zaten collect() fonksiyonunun ne döndürdüğünü kusursuz anlıyor.
        $nodes = collect($workflow->nodes ?? []);
        $instanceData = (array) ($task->processInstance->data ?? []);
        $entries = [];

        $submissions = FormSubmission::query()
            ->where('process_instance_id', $task->process_instance_id)
            ->whereNotNull('task_id')
            ->with(['task', 'submitter'])
            ->orderBy('created_at')
            ->get();

        foreach ($submissions as $submission) {
            // PHP0406 HATASININ ÇÖZÜMÜ: Native PHP Assert kullanımı.
            // Bu satır IDE'ye "Sana garanti veriyorum bu bir FormSubmission nesnesidir" der.
            // PHPDoc gibi kafasını karıştırmaz.
            assert($submission instanceof FormSubmission);

            if (! $submission->task || $submission->task->type !== 'form') {
                continue;
            }

            if ($submission->task_id === $task->id && $task->status === 'pending' && $task->type === 'form') {
                continue;
            }

            $entry = $this->buildFormEntryFromSubmission($submission, $nodes);
            if ($entry) {
                $entries[] = $entry;
            }
        }

        $submittedTaskIds = $submissions->pluck('task_id')->filter()->all();

        $completedFormTasks = Task::query()
            ->where('process_instance_id', $task->process_instance_id)
            ->where('type', 'form')
            ->where('status', 'completed')
            ->when(
                $task->type === 'form' && $task->status === 'pending',
                fn($query) => $query->where('id', '!=', $task->id)
            )
            ->orderBy('completed_at')
            ->get();

        foreach ($completedFormTasks as $formTask) {
            // Aynı garanti burada da Task nesnesi için veriliyor
            assert($formTask instanceof Task);

            if (in_array($formTask->id, $submittedTaskIds, true)) {
                continue;
            }

            $node = $nodes->firstWhere('id', $formTask->node_id);
            $subFormId = $node['data']['subFormId'] ?? null;
            if (! $subFormId) {
                continue;
            }

            $template = FormTemplate::find($subFormId);
            if (! $template) {
                continue;
            }

            $data = $this->extractFormDataForTemplate($instanceData, $template);
            if (empty($data)) {
                continue;
            }

            $entries[] = [
                'node_label' => $node['data']['label'] ?? $node['label'] ?? $template->name,
                'form' => $template,
                'data' => $data,
                'submitted_at' => $formTask->completed_at?->toIso8601String(),
                'submitted_by' => null,
                'comment' => $formTask->comment,
            ];
        }

        return array_values($entries);
    }

    private function buildFormEntryFromSubmission(FormSubmission $submission, \Illuminate\Support\Collection $nodes): ?array
    {
        $node = $nodes->firstWhere('id', $submission->task?->node_id);
        $subFormId = $node['data']['subFormId'] ?? null;

        if (! $subFormId) {
            return null;
        }

        $template = FormTemplate::find($subFormId);
        if (! $template) {
            return null;
        }

        return [
            'node_label' => $node['data']['label'] ?? $node['label'] ?? $template->name,
            'form' => $template,
            'data' => $submission->data ?? [],
            'submitted_at' => $submission->created_at?->toIso8601String(),
            'submitted_by' => $submission->submitter?->name,
            'comment' => $submission->task?->comment,
        ];
    }

    private function buildFormEntryFromData(FormTemplate $template, array $instanceData, string $label, ?string $submittedBy): ?array
    {
        $data = $this->extractFormDataForTemplate($instanceData, $template);

        if (empty($data)) {
            return null;
        }

        return [
            'node_label' => $label,
            'form' => $template,
            'data' => $data,
            'submitted_at' => null,
            'submitted_by' => $submittedBy,
        ];
    }

    private function extractFormDataForTemplate(array $instanceData, FormTemplate $template): array
    {
        $fieldIds = collect($template->schema ?? [])
            ->pluck('id')
            ->filter()
            ->all();

        if (empty($fieldIds)) {
            return [];
        }

        $extraIds = [];
        foreach ($fieldIds as $fId) {
            $extraIds[] = $fId . '_note';
        }
        $allIds = array_merge($fieldIds, $extraIds);

        return collect($instanceData)
            ->only($allIds)
            ->filter(fn($value) => $value !== null && $value !== '')
            ->all();
    }

    private function formEntryExists(array $entries, array $candidate): bool
    {
        foreach ($entries as $entry) {
            if (($entry['form']['id'] ?? null) === ($candidate['form']['id'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getPreviousTaskNotesForTask(Task $task): array
    {
        $nodes = collect($task->processInstance->workflow->nodes ?? []);

        return Task::query()
            ->where('process_instance_id', $task->process_instance_id)
            ->when(
                $task->status === 'pending',
                fn($query) => $query->where('id', '!=', $task->id)
            )
            ->where('type', '!=', 'form')
            ->whereIn('status', ['completed', 'rejected', 'revised'])
            ->whereNotNull('comment')
            ->where('comment', '!=', '')
            ->with(['assignedUser'])
            ->orderBy('completed_at')
            ->get()
            ->map(function (Task $previousTask) use ($nodes) {
                $node = $nodes->firstWhere('id', $previousTask->node_id);
                $submission = FormSubmission::query()
                    ->where('task_id', $previousTask->id)
                    ->with('submitter')
                    ->first();

                $author = $submission?->submitter?->name ?? $previousTask->assignedUser?->name;

                return [
                    'node_label' => $node['data']['label'] ?? $node['label'] ?? 'Görev',
                    'task_type' => $previousTask->type,
                    'status' => $previousTask->status,
                    'comment' => $previousTask->comment,
                    'author' => $author,
                    'completed_at' => $previousTask->completed_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }
    public function exportPdf(Task $task)
    {
        /** @var User $user */
        $user = Auth::user();
        $isAdmin = $user->hasRole('Admin') || $user->hasRole('superadmin');

        $isAssigned = $isAdmin || TaskVisibility::userCanAccessTask($user, $task) || ($task->status !== 'pending' && $task->completed_by === $user->id);

        if (!$isAssigned) {
            abort(403, 'Bu görevi dışa aktarma yetkiniz yok.');
        }

        $task->load('processInstance.workflow.formTemplate');
        $workflowNode = collect($task->processInstance->workflow->nodes ?? [])->firstWhere('id', $task->node_id);
        $subFormId = $workflowNode['data']['subFormId'] ?? null;
        $subForm = $subFormId ? FormTemplate::find($subFormId) : null;

        if (!$subForm) {
            return back()->with('error', 'Bu görev için tanımlı bir form bulunamadı.');
        }

        $instanceData = (array) ($task->processInstance->data ?? []);
        $formData = $this->extractFormDataForTemplate($instanceData, $subForm);

        $elements = $subForm->schema ?? [];
        $formattedFormData = [];
        foreach ($elements as $el) {
            $note = $formData[$el['id'] . '_note'] ?? null;
            $formattedFormData[$el['id']] = $this->formatFieldValueForExport($formData[$el['id']] ?? null, $note);
        }

        $paperSize = ($subForm->paper_size === 'a5') ? 'a5' : 'a4';
        $logoBase64 = \App\Services\CertificateGenerator::getLogoBase64();

        $pdf = Pdf::loadView('pdf.task-detail', [
            'templateName' => $subForm->name,
            'description'  => $subForm->description,
            'documentNo'   => $subForm->document_no,
            'publishDate'  => $subForm->publish_date,
            'revisionNo'   => $subForm->revision_no,
            'revisionDate' => $subForm->revision_date,
            'pageNo'       => $subForm->page_no ?? 1,
            'paperSize'    => $paperSize,
            'logoBase64'   => $logoBase64,
            'elements'     => $elements,
            'formData'     => $formattedFormData,
            'rawFormData'  => $formData,
        ])->setPaper($paperSize, 'portrait');

        $fileName = Str::slug($subForm->name) . '-' . $task->id . '.pdf';
        return $pdf->download($fileName);
    }

    public function exportExcel(Task $task)
    {
        /** @var User $user */
        $user = Auth::user();
        $isAdmin = $user->hasRole('Admin') || $user->hasRole('superadmin');

        $isAssigned = $isAdmin || TaskVisibility::userCanAccessTask($user, $task) || ($task->status !== 'pending' && $task->completed_by === $user->id);

        if (!$isAssigned) {
            abort(403, 'Bu görevi dışa aktarma yetkiniz yok.');
        }

        $task->load('processInstance.workflow');
        $workflowNode = collect($task->processInstance->workflow->nodes ?? [])->firstWhere('id', $task->node_id);
        $subFormId = $workflowNode['data']['subFormId'] ?? null;
        $subForm = $subFormId ? FormTemplate::find($subFormId) : null;

        if (!$subForm) {
            return back()->with('error', 'Bu görev için tanımlı bir form bulunamadı.');
        }

        $instanceData = (array) ($task->processInstance->data ?? []);
        $formData = $this->extractFormDataForTemplate($instanceData, $subForm);

        // Excel için başlıkları Element Label'larına (Sorulara) çevir
        $excelData = [];
        $elements = $subForm->schema ?? [];
        foreach ($elements as $el) {
            if ($el['type'] === 'header') continue;

            $note = $formData[$el['id'] . '_note'] ?? null;
            $excelData[$el['label']] = $this->formatFieldValueForExport($formData[$el['id']] ?? null, $note);
        }

        $fileName = Str::slug($subForm->name) . '-' . $task->id . '.xlsx';
        return Excel::download(new TaskFormExport($excelData, $subForm->name), $fileName);
    }

    public function exportCertificate(Task $task, \App\Services\CertificateGenerator $generator)
    {
        /** @var User $user */
        $user = Auth::user();
        $isAdmin = $user->hasRole('Admin') || $user->hasRole('superadmin');

        $isAssigned = $isAdmin
            || TaskVisibility::userCanAccessTask($user, $task)
            || ($task->status !== 'pending' && $task->completed_by === $user->id)
            || $task->processInstance->started_by === $user->id;

        if (!$isAssigned) {
            abort(403, 'Bu analiz sertifikasını indirme yetkiniz bulunmuyor.');
        }

        $pdf = $generator->generate($task->processInstance);
        $fileName = 'Analiz-Sertifikasi-PI-' . $task->process_instance_id . '.pdf';

        return $pdf->download($fileName);
    }

    private function formatFieldValueForExport($val, ?string $note = null): string
    {
        if (is_null($val) || $val === '') {
            return '-';
        }

        if (is_bool($val)) {
            $text = $val ? 'Evet' : 'Hayır';
            if (!empty($note)) {
                $text .= ' (Not: ' . $note . ')';
            }
            return $text;
        }

        if (is_array($val)) {
            if (isset($val['start']) || isset($val['end'])) {
                $start = !empty($val['start']) ? \Carbon\Carbon::parse($val['start'])->format('d.m.Y') : '';
                $end = !empty($val['end']) ? \Carbon\Carbon::parse($val['end'])->format('d.m.Y') : '';
                $text = trim($start . ' - ' . $end, ' -');
                if (!empty($val['note'])) {
                    $text .= ' (Not: ' . $val['note'] . ')';
                }
                return empty($text) ? '-' : $text;
            }

            if (isset($val['date'])) {
                $date = !empty($val['date']) ? \Carbon\Carbon::parse($val['date'])->format('d.m.Y') : '';
                if (!empty($val['note'])) {
                    $date .= ' (Not: ' . $val['note'] . ')';
                }
                return empty($date) ? '-' : $date;
            }

            if (isset($val['decision'])) {
                $dec = $val['decision'];
                $n = $val['note'] ?? $note ?? '';
                return !empty($n) ? "{$dec} (Not: {$n})" : (string)$dec;
            }

            $items = [];
            foreach ($val as $sub) {
                if (is_array($sub)) {
                    if (isset($sub['start']) || isset($sub['end'])) {
                        $start = !empty($sub['start']) ? \Carbon\Carbon::parse($sub['start'])->format('d.m.Y') : '';
                        $end = !empty($sub['end']) ? \Carbon\Carbon::parse($sub['end'])->format('d.m.Y') : '';
                        $itemText = trim($start . ' - ' . $end, ' -');
                        if (!empty($sub['note'])) $itemText .= ' (Not: ' . $sub['note'] . ')';
                        $items[] = $itemText;
                    } elseif (isset($sub['date'])) {
                        $itemText = !empty($sub['date']) ? \Carbon\Carbon::parse($sub['date'])->format('d.m.Y') : '';
                        if (!empty($sub['note'])) $itemText .= ' (Not: ' . $sub['note'] . ')';
                        $items[] = $itemText;
                    } else {
                        $items[] = json_encode($sub, JSON_UNESCAPED_UNICODE);
                    }
                } else {
                    $items[] = (string)$sub;
                }
            }
            return empty($items) ? '-' : implode('; ', $items);
        }

        $res = (string)$val;
        if (!empty($note)) {
            $res .= ' (Not: ' . $note . ')';
        }
        return $res;
    }

    /**
     * Yönetici Görev Düzenleme (Admin Edit)
     */
    public function adminUpdate(Request $request, Task $task, \App\Services\ProcessAuditService $auditService)
    {
        $user = $request->user() ?? auth()->user();
        if (!$user || (!$user->can('tasks.admin_edit') && !$user->hasRole('Admin') && !$user->hasRole('superadmin'))) {
            abort(403, 'Görevi yönetici olarak düzenleme yetkiniz bulunmamaktadır.');
        }

        $validated = $request->validate([
            'assigned_to' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
            'status' => 'nullable|in:pending,completed,cancelled',
            'comment' => 'nullable|string|max:1000',
        ]);

        $instance = $task->processInstance;
        $changes = [];

        if (array_key_exists('assigned_to', $validated) && $validated['assigned_to'] != $task->assigned_to) {
            $oldUser = User::find($task->assigned_to)?->name ?? 'Atanmamış';
            $newUser = User::find($validated['assigned_to'])?->name ?? 'Atanmamış';
            $changes[] = "Atanan: {$oldUser} -> {$newUser}";
            $task->assigned_to = $validated['assigned_to'];
        }

        if (array_key_exists('due_date', $validated)) {
            $newDue = $validated['due_date'] ? \Carbon\Carbon::parse($validated['due_date']) : null;
            $oldDueStr = $task->due_date ? $task->due_date->format('d.m.Y H:i') : 'Yok';
            $newDueStr = $newDue ? $newDue->format('d.m.Y H:i') : 'Yok';
            if ($oldDueStr !== $newDueStr) {
                $changes[] = "Vade: {$oldDueStr} -> {$newDueStr}";
                $task->due_date = $newDue;
            }
        }

        if (array_key_exists('status', $validated) && $validated['status'] !== $task->status) {
            $changes[] = "Durum: {$task->status} -> {$validated['status']}";
            $task->status = $validated['status'];
            if ($validated['status'] === 'completed' && !$task->completed_at) {
                $task->completed_at = now();
                $task->completed_by = $user->id;
            }
        }

        if (!empty($validated['comment'])) {
            $task->comment = $validated['comment'];
        }

        $task->save();

        if ($instance && !empty($changes)) {
            $auditService->recordAdminIntervention(
                $instance,
                'admin_task_edit',
                $user->id,
                'Görev Yönetici Güncellemesi',
                null,
                implode(', ', $changes),
                $validated['comment'] ?? 'Yönetici tarafından güncellendi',
                $task->id
            );
        }

        return back()->with('success', "Görev (#{$task->id}) yönetici tarafından başarıyla güncellendi.");
    }

    /**
     * Yönetici Görev & Süreç Silme (Admin Soft Delete)
     */
    public function adminDestroy(Request $request, Task $task, \App\Services\ProcessAuditService $auditService)
    {
        $user = $request->user() ?? auth()->user();
        if (!$user || (!$user->can('tasks.admin_delete') && !$user->hasRole('Admin') && !$user->hasRole('superadmin'))) {
            abort(403, 'Görev veya süreç silme yetkiniz bulunmamaktadır.');
        }

        $scope = $request->input('delete_scope', 'process'); // 'process' | 'task'
        $instance = $task->processInstance;

        if ($scope === 'process' && $instance) {
            // Süreç ve bağlı tüm görevleri soft-delete yap
            $taskCount = $instance->tasks()->count();
            $instance->tasks()->delete(); // Soft delete all tasks
            $instance->delete(); // Soft delete process instance

            return back()->with('success', "Test süreci (#{$instance->id} - {$instance->workflow?->name}) ve bağlı {$taskCount} adet görevi güvenle arşivlendi (Soft Delete).");
        } else {
            // Yalnızca tek görevi soft-delete yap
            $task->delete();
            return back()->with('success', "Görev (#{$task->id}) güvenle arşivlendi (Soft Delete).");
        }
    }

    /**
     * Yönetici Toplu Görev & Süreç Silme (Admin Bulk Soft Delete)
     */
    public function adminBulkDestroy(Request $request)
    {
        $user = $request->user() ?? auth()->user();
        if (!$user || (!$user->can('tasks.admin_bulk_delete') && !$user->hasRole('Admin') && !$user->hasRole('superadmin'))) {
            abort(403, 'Toplu görev veya süreç silme yetkiniz bulunmamaktadır.');
        }

        $validated = $request->validate([
            'task_ids' => 'required|array|min:1',
            'task_ids.*' => 'integer',
            'delete_scope' => 'nullable|in:process,task',
        ]);

        $scope = $validated['delete_scope'] ?? 'process';
        $tasks = Task::whereIn('id', $validated['task_ids'])->with('processInstance.workflow')->get();
        $deletedProcesses = [];
        $deletedTasksCount = 0;

        foreach ($tasks as $task) {
            if ($scope === 'process' && $task->processInstance) {
                $pId = $task->process_instance_id;
                if (!isset($deletedProcesses[$pId])) {
                    $task->processInstance->tasks()->delete();
                    $task->processInstance->delete();
                    $deletedProcesses[$pId] = true;
                }
            } else {
                $task->delete();
            }
            $deletedTasksCount++;
        }

        $msg = $scope === 'process'
            ? count($deletedProcesses) . " adet test süreci ve bağlı görevleri başarıyla arşivlendi (Soft Delete)."
            : "{$deletedTasksCount} adet görev başarıyla arşivlendi (Soft Delete).";

        return back()->with('success', $msg);
    }
}

