<?php

namespace App\Services;

use App\Models\FormSubmission;
use App\Models\ProcessInstance;
use App\Models\Task;
use App\Models\User;

class ProcessHistoryService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildForInstance(ProcessInstance $instance): array
    {
        $instance->loadMissing(['starter', 'workflow']);

        $nodes = collect($instance->workflow->nodes ?? []);
        $history = [];

        $history[] = [
            'at' => $instance->created_at?->toIso8601String(),
            'actor' => $instance->starter?->name ?? 'Sistem',
            'action' => 'Süreci başlattı',
            'node_label' => $this->nodeLabel($nodes, null, 'Başlangıç'),
            'comment' => null,
        ];

        $submissions = FormSubmission::query()
            ->where('process_instance_id', $instance->id)
            ->with('submitter')
            ->get()
            ->keyBy('task_id');

        $tasks = Task::query()
            ->where('process_instance_id', $instance->id)
            ->where('status', '!=', 'pending')
            ->whereNotNull('completed_at')
            ->with('assignedUser')
            ->orderBy('completed_at')
            ->get();

        foreach ($tasks as $task) {
            $submission = $submissions->get($task->id);
            $actor = $submission?->submitter?->name
                ?? $task->assignedUser?->name
                ?? ($task->assigned_role ? $task->assigned_role.' (rol)' : 'Bilinmeyen');

            $history[] = [
                'at' => $task->completed_at?->toIso8601String(),
                'actor' => $actor,
                'action' => $this->describeTaskAction($task),
                'node_label' => $this->nodeLabel($nodes, $task->node_id),
                'comment' => $task->comment,
            ];
        }

        if ($instance->status === 'completed') {
            $history[] = [
                'at' => $tasks->last()?->completed_at?->toIso8601String()
                    ?? $instance->updated_at?->toIso8601String(),
                'actor' => 'Sistem',
                'action' => 'Süreç tamamlandı',
                'node_label' => $this->nodeLabel($nodes, $instance->current_node_id, 'Bitiş'),
                'comment' => null,
            ];
        }

        if ($instance->status === 'cancelled') {
            $meta = (array) (($instance->data ?? [])['_cancellation'] ?? []);
            $cancelledBy = ! empty($meta['cancelled_by'])
                ? User::find($meta['cancelled_by'])
                : null;

            $history[] = [
                'at' => $meta['cancelled_at'] ?? $instance->updated_at?->toIso8601String(),
                'actor' => $cancelledBy?->name ?? 'Admin',
                'action' => 'Süreci iptal etti',
                'node_label' => 'İptal',
                'comment' => $meta['reason'] ?? null,
            ];
        }

        return collect($history)
            ->filter(fn (array $entry) => ! empty($entry['at']))
            ->sortBy('at')
            ->values()
            ->all();
    }

    private function nodeLabel($nodes, ?string $nodeId, string $fallback = 'Adım'): string
    {
        if (! $nodeId) {
            $startNode = $nodes->first(function ($node) {
                $taskType = $node['data']['taskType'] ?? null;
                $type = $node['type'] ?? null;

                return $taskType === 'start' || in_array($type, ['input', 'start'], true);
            });

            if ($fallback === 'Başlangıç' && $startNode) {
                return $startNode['data']['label'] ?? $startNode['label'] ?? $fallback;
            }

            return $fallback;
        }

        $node = $nodes->firstWhere('id', $nodeId);

        return $node['data']['label'] ?? $node['data']['customName'] ?? $node['label'] ?? $fallback;
    }

    private function describeTaskAction(Task $task): string
    {
        if ($task->status === 'rejected') {
            return 'Reddetti';
        }

        if ($task->status === 'revised') {
            return 'Revize istedi';
        }

        if ($task->status === 'cancelled') {
            return 'Görev iptal edildi';
        }

        return match ($task->type) {
            'form' => 'Formu gönderdi',
            'review' => 'İncelemeyi tamamladı',
            default => 'Onayladı',
        };
    }
}
