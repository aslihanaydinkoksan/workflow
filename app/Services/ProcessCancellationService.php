<?php

namespace App\Services;

use App\Models\ProcessInstance;
use App\Models\Task;
use Illuminate\Validation\ValidationException;

class ProcessCancellationService
{
    public function cancel(ProcessInstance $instance, int $cancelledByUserId, ?string $reason = null): ProcessInstance
    {
        if ($instance->status === 'cancelled') {
            throw ValidationException::withMessages([
                'process' => 'Bu süreç zaten iptal edilmiş.',
            ]);
        }

        if ($instance->status === 'completed') {
            throw ValidationException::withMessages([
                'process' => 'Tamamlanmış bir süreç iptal edilemez.',
            ]);
        }

        Task::query()
            ->where('process_instance_id', $instance->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'rejected',
                'completed_at' => now(),
            ]);

        $data = (array) ($instance->data ?? []);
        $data['_cancellation'] = [
            'cancelled_at' => now()->toIso8601String(),
            'cancelled_by' => $cancelledByUserId,
            'reason' => $reason,
        ];

        $instance->update([
            'status' => 'cancelled',
            'data' => $data,
        ]);

        return $instance->fresh();
    }
}
