<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProcessInstance;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LeadTimeService
{
    /**
     * Belirli bir süreç örneğinin adım adım süre ve darboğaz analizini üretir.
     */
    public function analyzeProcess(ProcessInstance $instance): array
    {
        $tasks = $instance->tasks()
            ->with(['assignedUser', 'completedUser'])
            ->orderBy('created_at', 'asc')
            ->get();

        $steps = [];
        $totalMinutes = 0;
        $maxDurationMinutes = 0;
        $bottleneckStep = null;

        foreach ($tasks as $task) {
            $created = Carbon::parse($task->created_at);
            $completed = $task->completed_at ? Carbon::parse($task->completed_at) : null;
            $now = Carbon::now();

            $isCompleted = !is_null($completed);
            $durationMinutes = $isCompleted 
                ? max(1, $created->diffInMinutes($completed)) 
                : max(1, $created->diffInMinutes($now));

            $totalMinutes += $durationMinutes;

            $nodeTitle = $task->title ?: ($task->node_id ?: 'Adım #' . $task->id);
            if (str_starts_with($nodeTitle, 'node_')) {
                $nodeTitle = ucwords(str_replace('_', ' ', str_replace('node_', '', $nodeTitle)));
            }

            $stepData = [
                'task_id' => $task->id,
                'node_id' => $task->node_id,
                'title' => $nodeTitle,
                'type' => $task->type,
                'status' => $task->status,
                'is_completed' => $isCompleted,
                'assigned_user' => $task->assignedUser?->name ?: 'Havuz / Belirtilmedi',
                'completed_user' => $task->completedUser?->name ?: ($isCompleted ? ($task->assignedUser?->name ?: 'Sistem') : null),
                'created_at' => $created->format('d.m.Y H:i'),
                'completed_at' => $completed ? $completed->format('d.m.Y H:i') : null,
                'duration_minutes' => $durationMinutes,
                'duration_formatted' => $this->formatDuration($durationMinutes),
                'duration_hours' => round($durationMinutes / 60, 1),
                'is_bottleneck' => false,
                'percentage' => 0,
            ];

            if ($durationMinutes > $maxDurationMinutes) {
                $maxDurationMinutes = $durationMinutes;
                $bottleneckStep = $task->id;
            }

            $steps[] = $stepData;
        }

        // Yüzdeleri ve darboğaz etiketini hesapla
        foreach ($steps as &$step) {
            $step['percentage'] = $totalMinutes > 0 
                ? round(($step['duration_minutes'] / $totalMinutes) * 100, 1) 
                : 0;

            if ($step['task_id'] === $bottleneckStep && count($steps) > 1 && $step['duration_minutes'] > 5) {
                $step['is_bottleneck'] = true;
                $step['badge_color'] = 'bg-rose-100 text-rose-800 border-rose-300';
            } elseif ($step['duration_minutes'] < 120) {
                $step['badge_color'] = 'bg-emerald-100 text-emerald-800 border-emerald-300';
            } else {
                $step['badge_color'] = 'bg-amber-100 text-amber-800 border-amber-300';
            }
        }
        unset($step);

        $bottleneckInfo = collect($steps)->firstWhere('is_bottleneck', true);

        return [
            'total_duration_minutes' => $totalMinutes,
            'total_duration_formatted' => $this->formatDuration($totalMinutes),
            'total_steps_count' => count($steps),
            'completed_steps_count' => collect($steps)->where('is_completed', true)->count(),
            'bottleneck_step' => $bottleneckInfo,
            'average_step_minutes' => count($steps) > 0 ? round($totalMinutes / count($steps)) : 0,
            'average_step_formatted' => count($steps) > 0 ? $this->formatDuration((int)round($totalMinutes / count($steps))) : '0 dk',
            'steps' => $steps,
        ];
    }

    /**
     * Dakikayı insan tarafından okunabilir kurumsal formata çevirir.
     */
    private function formatDuration(int|float $minutes): string
    {
        $minutes = (int) round($minutes);
        if ($minutes < 60) {
            return "{$minutes} dk";
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($hours < 24) {
            return $remainingMinutes > 0 ? "{$hours} saat {$remainingMinutes} dk" : "{$hours} saat";
        }

        $days = floor($hours / 24);
        $remainingHours = $hours % 24;

        return $remainingHours > 0 ? "{$days} gün {$remainingHours} saat" : "{$days} gün";
    }
}
