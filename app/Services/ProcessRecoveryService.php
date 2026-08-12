<?php

namespace App\Services;

use App\Models\ProcessInstance;
use App\Models\Task;

class ProcessRecoveryService
{
    public function __construct(
        private ProcessEngine $processEngine,
        private TaskManager $taskManager,
    ) {
    }

    /**
     * Tamamlanmış görev var ama aynı adımda bekleyen kalmış süreçleri ilerletir.
     */
    public function recoverStuckInstances(): int
    {
        $recovered = 0;

        ProcessInstance::query()
            ->whereIn('status', ['waiting', 'running'])
            ->with('tasks')
            ->orderBy('id')
            ->chunkById(50, function ($instances) use (&$recovered) {
                foreach ($instances as $instance) {
                    if ($this->recoverInstance($instance)) {
                        $recovered++;
                    }
                }
            });

        return $recovered;
    }

    public function recoverInstance(ProcessInstance $instance): bool
    {
        $instance->load('tasks');

        $nodeIds = $instance->tasks
            ->pluck('node_id')
            ->unique()
            ->values();

        foreach ($nodeIds as $nodeId) {
            $nodeTasks = $instance->tasks->where('node_id', $nodeId);
            $hasCompleted = $nodeTasks->contains(fn (Task $task) => in_array($task->status, ['completed', 'rejected'], true));
            $hasPending = $nodeTasks->contains(fn (Task $task) => $task->status === 'pending');

            if (! $hasCompleted || ! $hasPending) {
                continue;
            }

            $this->taskManager->cancelPendingTasksForNode($instance, $nodeId);

            $instance->update([
                'current_node_id' => $nodeId,
                'status' => 'running',
            ]);

            $this->processEngine->onTaskCompleted($instance->fresh(), 'approved');

            return true;
        }

        return false;
    }
}
