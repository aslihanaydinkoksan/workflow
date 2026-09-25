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

        return Inertia::render('Tasks/Index', [
            'tasks'                => $tasks,
            'completedTasks'       => $completedTasks,
            'users'                => $users, // Yeni Prop
            'given_delegations'    => $givenDelegations, // Yeni Prop
            'received_delegations' => $receivedDelegations, // Yeni Prop
            'pending_follow_ups'   => $pendingFollowUps,
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

        $previousForms = $this->getPreviousFormsForTask($task);
        $instanceData = (array) ($task->processInstance->data ?? []);

        /** @var FormTemplate|null $workflowStartForm */
        $workflowStartForm = $task->processInstance->workflow->formTemplate;

        if ($workflowStartForm && ! empty($instanceData)) {
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

        return Inertia::render('Tasks/Show', [
            'task' => $task,
            'nodeData' => $workflowNode,
            'subForm' => $subForm,
            'initialData' => $instanceData,
            'previousForms' => $previousForms,
            'previousTaskNotes' => $previousTaskNotes,
            'prefilledFormData' => $prefilledFormData,
            'appLogo' => \App\Models\Setting::where('key', 'app_logo')->value('value'),
        ]);
    }

    public function update(Request $request, Task $task, ProcessEngine $engine, TaskManager $manager)
    {
        /** @var User $user */
        $user = Auth::user();
        $isAssigned = TaskVisibility::userCanAccessTask($user, $task);

        if (!$isAssigned) {
            abort(403);
        }

        $action = $request->input('task_action', $request->input('action'));
        $comment = $request->input('comment');
        $formData = $request->input('answers', $request->input('data'));

        // Sub-form verisi geldiyse ProcessInstance içine kaydet
        if (is_array($formData) && ($task->type === 'form' || ! empty($formData))) {
            $instance = $task->processInstance;
            $mergedData = array_merge((array) $instance->data, $formData);
            $instance->update(['data' => $mergedData]);

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

        // KOŞULLU DOĞRULAMA (İş Akışı Elemanları Onay Düğümü Doğrulaması)
        if ($action !== 'reject') {
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

        if ($action === 'reject') {
            $manager->rejectTask($task, $comment);
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
            $manager->completeTask($task, $comment);
            $manager->cancelPendingTasksForNode($task->processInstance, $task->node_id, $task->id);
            $edgeAction = 'revised';
            $shouldAdvance = true;
        } else {
            $manager->completeTask($task, $comment);
            $edgeAction = 'approved';
            // Aynı adımda roldeki herhangi biri onaylayınca sıradaki adıma geç
            $manager->cancelPendingTasksForNode($task->processInstance, $task->node_id, $task->id);
            $shouldAdvance = true;
        }

        if ($shouldAdvance) {
            $instance = $task->processInstance->fresh();
            $instance->update([
                'current_node_id' => $task->node_id,
                'status' => 'running',
            ]);
            $engine->onTaskCompleted($instance, $edgeAction);
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
            $message = $action === 'reject'
                ? 'Görev reddedildi. Süreç, tasarımdaki Red bağlantısına göre yönlendirildi.'
                : 'Görev başarıyla tamamlandı. Süreç otomatik olarak sıradaki adıma geçti.';
        } else {
            $task->processInstance->update(['status' => 'waiting']);
            $message = 'Göreviniz kaydedildi.';
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

        return collect($instanceData)
            ->only($fieldIds)
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

        $pdf = Pdf::loadView('pdf.task-detail', [
            'templateName' => $subForm->name,
            'description'  => $subForm->description,
            'documentNo'   => $subForm->document_no,
            'publishDate'  => $subForm->publish_date,
            'revisionNo'   => $subForm->revision_no,
            'revisionDate' => $subForm->revision_date,
            'elements'     => $subForm->schema ?? [],
            'formData'     => $formData,
        ]);

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

            $val = $formData[$el['id']] ?? '';
            if (is_array($val)) $val = implode(', ', $val);
            elseif (is_bool($val)) $val = $val ? 'Evet' : 'Hayır';

            $excelData[$el['label']] = empty($val) ? '-' : $val;
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
}
