<?php

namespace App\Services;

use App\Mail\WorkflowNotificationMail;
use App\Models\ProcessInstance;
use App\Models\Task;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function taskAssigned(Task $task): void
    {
        if (! $task->assigned_to || $task->status !== 'pending') {
            return;
        }

        $task->loadMissing(['processInstance.workflow', 'assignedUser']);
        $user = $task->assignedUser;

        if (! $user) {
            return;
        }

        $workflowName = $task->processInstance?->workflow?->name ?? 'Süreç';
        $nodeLabel = $this->nodeLabel($task);

        $this->send(
            $user,
            'task_assigned',
            'Yeni görev atandı',
            "{$workflowName} sürecinde size yeni bir görev atandı: {$nodeLabel}.",
            $task,
            [
                'action_url' => route('tasks.show', $task->id),
                'workflow_name' => $workflowName,
                'node_label' => $nodeLabel,
            ]
        );
    }

    public function taskRejected(ProcessInstance $instance, Task $task, User $rejectedBy, ?string $comment = null): void
    {
        $instance->loadMissing(['workflow', 'starter']);
        $starter = $instance->starter;

        if (! $starter || $starter->id === $rejectedBy->id) {
            return;
        }

        $workflowName = $instance->workflow?->name ?? 'Süreç';
        $nodeLabel = $this->nodeLabel($task);
        $body = "{$rejectedBy->name}, {$workflowName} sürecindeki \"{$nodeLabel}\" adımını reddetti.";

        if ($comment) {
            $body .= "\n\nGerekçe: {$comment}";
        }

        $this->send(
            $starter,
            'task_rejected',
            'Sürecinizde red işlemi yapıldı',
            $body,
            $task,
            [
                'action_url' => route('processes.tracker', $instance->id),
                'workflow_name' => $workflowName,
                'node_label' => $nodeLabel,
                'rejected_by' => $rejectedBy->name,
            ]
        );
    }

    public function sendDueDateReminders(): int
    {
        $count = 0;

        $tasks = Task::query()
            ->where('status', 'pending')
            ->whereNotNull('assigned_to')
            ->whereNotNull('due_date')
            ->with(['processInstance.workflow', 'assignedUser'])
            ->get();

        foreach ($tasks as $task) {
            $count += $this->processDueDateRemindersForTask($task);
        }

        return $count;
    }

    private function processDueDateRemindersForTask(Task $task): int
    {
        $user = $task->assignedUser;

        if (! $user || ! $task->due_date) {
            return 0;
        }

        $count = 0;
        $dueDate = $task->due_date->copy();
        $now = now();

        if ($dueDate->isFuture()) {
            $hoursRemaining = $now->diffInMinutes($dueDate, false) / 60;
            $thresholds = $this->dueReminderHours();

            foreach ($thresholds as $index => $hours) {
                $lowerBound = $index < count($thresholds) - 1
                    ? $thresholds[$index + 1]
                    : 0;

                if ($hoursRemaining > $hours || $hoursRemaining <= $lowerBound) {
                    continue;
                }

                $reminderKey = "before_{$hours}h";

                if ($this->reminderAlreadySent($user, $task, $reminderKey)) {
                    continue;
                }

                if ($this->notifyBeforeDue($task, $user, $hours, $reminderKey)) {
                    $count++;
                }

                break;
            }

            return $count;
        }

        if (! $this->reminderAlreadySent($user, $task, 'expired')) {
            if ($this->notifyExpired($task, $user)) {
                $count++;
            }
        }

        $intervalHours = (int) config('notifications.overdue_reminder_interval_hours', 24);

        if ($intervalHours <= 0) {
            return $count;
        }

        $lastOverdueReminder = $this->lastOverdueReminderAt($user, $task);
        $shouldRepeat = ! $lastOverdueReminder
            || $lastOverdueReminder->lte($now->copy()->subHours($intervalHours));

        if ($shouldRepeat && $this->reminderAlreadySent($user, $task, 'expired')) {
            if ($this->notifyOverdueRepeat($task, $user, $intervalHours)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return list<int>
     */
    private function dueReminderHours(): array
    {
        $hours = config('notifications.due_reminder_hours', [24, 12, 6, 1]);

        return collect($hours)
            ->map(fn ($value) => (int) $value)
            ->filter(fn ($value) => $value > 0)
            ->sortDesc()
            ->values()
            ->all();
    }

    private function notifyBeforeDue(Task $task, User $user, int $hours, string $reminderKey): bool
    {
        $workflowName = $task->processInstance?->workflow?->name ?? 'Süreç';
        $nodeLabel = $this->nodeLabel($task);
        $dueText = $this->formatDueDate($task);
        $remainingText = $this->formatRemainingTime($task);

        $hourLabel = $hours === 1 ? '1 saat' : "{$hours} saat";

        return $this->send(
            $user,
            'task_due_soon',
            "Görev süresine {$hourLabel} kaldı",
            "{$workflowName} sürecindeki \"{$nodeLabel}\" görevinin süresi {$dueText} tarihinde dolacak. Kalan süre: {$remainingText}.",
            $task,
            [
                'action_url' => route('tasks.show', $task->id),
                'workflow_name' => $workflowName,
                'node_label' => $nodeLabel,
                'due_date' => $dueText,
                'reminder_key' => $reminderKey,
                'hours_remaining' => $hours,
            ],
            reminderKey: $reminderKey
        ) !== null;
    }

    private function notifyExpired(Task $task, User $user): bool
    {
        $workflowName = $task->processInstance?->workflow?->name ?? 'Süreç';
        $nodeLabel = $this->nodeLabel($task);
        $dueText = $this->formatDueDate($task);

        return $this->send(
            $user,
            'task_overdue',
            'Görev süresi doldu',
            "{$workflowName} sürecindeki \"{$nodeLabel}\" görevinin süresi {$dueText} tarihinde doldu. Lütfen görevi tamamlayın.",
            $task,
            [
                'action_url' => route('tasks.show', $task->id),
                'workflow_name' => $workflowName,
                'node_label' => $nodeLabel,
                'due_date' => $dueText,
                'reminder_key' => 'expired',
            ],
            reminderKey: 'expired'
        ) !== null;
    }

    private function notifyOverdueRepeat(Task $task, User $user, int $intervalHours): bool
    {
        $workflowName = $task->processInstance?->workflow?->name ?? 'Süreç';
        $nodeLabel = $this->nodeLabel($task);
        $dueText = $this->formatDueDate($task);
        $overdueText = $this->formatOverdueDuration($task);
        $reminderKey = 'overdue_'.now()->format('YmdHi');

        return $this->send(
            $user,
            'task_overdue',
            'Geciken görev hatırlatması',
            "{$workflowName} sürecindeki \"{$nodeLabel}\" görevi hâlâ bekliyor. Süre {$dueText} tarihinde dolmuştu ({$overdueText} önce).",
            $task,
            [
                'action_url' => route('tasks.show', $task->id),
                'workflow_name' => $workflowName,
                'node_label' => $nodeLabel,
                'due_date' => $dueText,
                'reminder_key' => $reminderKey,
                'repeat_interval_hours' => $intervalHours,
            ]
        ) !== null;
    }

    private function reminderAlreadySent(User $user, Task $task, string $reminderKey): bool
    {
        if (str_starts_with($reminderKey, 'overdue_')) {
            return false;
        }

        return UserNotification::query()
            ->where('user_id', $user->id)
            ->where('task_id', $task->id)
            ->whereIn('type', ['task_due_soon', 'task_overdue'])
            ->where('data->reminder_key', $reminderKey)
            ->exists();
    }

    private function lastOverdueReminderAt(User $user, Task $task): ?\Illuminate\Support\Carbon
    {
        $notification = UserNotification::query()
            ->where('user_id', $user->id)
            ->where('task_id', $task->id)
            ->where('type', 'task_overdue')
            ->latest('id')
            ->first();

        return $notification?->created_at;
    }

    private function formatDueDate(Task $task): string
    {
        return $task->due_date?->timezone(config('app.timezone'))->format('d.m.Y H:i') ?? '';
    }

    private function formatRemainingTime(Task $task): string
    {
        if (! $task->due_date || $task->due_date->isPast()) {
            return 'süresi doldu';
        }

        $totalMinutes = (int) now()->diffInMinutes($task->due_date, false);

        if ($totalMinutes < 60) {
            return $totalMinutes <= 1 ? '1 dakikadan az' : "{$totalMinutes} dakika";
        }

        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;

        if ($hours >= 24) {
            $days = intdiv($hours, 24);
            $hours = $hours % 24;

            return $hours > 0
                ? "{$days} gün {$hours} saat"
                : "{$days} gün";
        }

        return $minutes > 0
            ? "{$hours} saat {$minutes} dakika"
            : "{$hours} saat";
    }

    private function formatOverdueDuration(Task $task): string
    {
        if (! $task->due_date) {
            return '';
        }

        $totalMinutes = (int) $task->due_date->diffInMinutes(now(), false);

        if ($totalMinutes < 60) {
            return $totalMinutes <= 1 ? '1 dakika' : "{$totalMinutes} dakika";
        }

        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;

        if ($hours >= 24) {
            $days = intdiv($hours, 24);
            $hours = $hours % 24;

            return $hours > 0
                ? "{$days} gün {$hours} saat"
                : "{$days} gün";
        }

        return $minutes > 0
            ? "{$hours} saat {$minutes} dakika"
            : "{$hours} saat";
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function send(
        User $user,
        string $type,
        string $title,
        string $body,
        ?Task $task = null,
        array $data = [],
        int $deduplicateHours = 0,
        ?string $reminderKey = null
    ): ?UserNotification {
        if ($reminderKey && $task && $this->reminderAlreadySent($user, $task, $reminderKey)) {
            return null;
        }

        if ($deduplicateHours > 0 && $task && ! $reminderKey) {
            $exists = UserNotification::query()
                ->where('user_id', $user->id)
                ->where('type', $type)
                ->where('task_id', $task->id)
                ->where('created_at', '>=', now()->subHours($deduplicateHours))
                ->exists();

            if ($exists) {
                return null;
            }
        }

        $notification = UserNotification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'task_id' => $task?->id,
        ]);

        if ($user->email) {
            try {
                Mail::to($user->email)->send(new WorkflowNotificationMail($notification));
                $notification->update(['emailed_at' => now()]);
            } catch (\Throwable $exception) {
                Log::warning('Workflow bildirim e-postası gönderilemedi.', [
                    'user_id' => $user->id,
                    'notification_id' => $notification->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $notification;
    }

    private function nodeLabel(Task $task): string
    {
        $nodes = collect($task->processInstance?->workflow?->nodes ?? []);
        $node = $nodes->firstWhere('id', $task->node_id);

        return $node['data']['label']
            ?? $node['data']['customName']
            ?? $node['label']
            ?? 'Görev';
    }
}
