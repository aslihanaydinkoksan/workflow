<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Delegation;
use App\Models\ProcessInstance;
use Illuminate\Support\Carbon;

class DelegationService
{
    /**
     * Kullanıcının aktif ve kapsam bazlı bir vekaleti olup olmadığını kontrol eder.
     * Varsa vekilin (delegatee) ID'sini, yoksa asıl kullanıcının ID'sini döner.
     *
     * @param int $userId Asıl görev atanan kişi (Delegator)
     * @param ProcessInstance|null $instance Süreç örneği (Kapsam kontrolü için)
     * @return int Nihai görev atanacak kişi
     */
    public function resolveAssignee(int $userId, ?ProcessInstance $instance = null): int
    {
        $delegation = $this->findActiveDelegation($userId, $instance);

        return $delegation ? (int) $delegation->delegatee_id : $userId;
    }

    /**
     * Aktif ve eşleşen vekalet kaydını döner.
     */
    public function findActiveDelegation(int $userId, ?ProcessInstance $instance = null): ?Delegation
    {
        $today = Carbon::today()->toDateString();

        $activeDelegations = Delegation::where('delegator_id', $userId)
            ->whereIn('status', ['active', 'approved'])
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->get();

        if ($activeDelegations->isEmpty()) {
            return null;
        }

        if ($instance) {
            $instance->loadMissing('workflow');
            $workflowId = (string) $instance->workflow_id;
            $workflowCategories = (array) ($instance->workflow?->category ?? []);

            // 1. Öncelik: Belirli bir akışa özel tanımlanmış vekalet (scope_type: workflow)
            $workflowMatch = $activeDelegations->first(function ($del) use ($workflowId) {
                return $del->scope_type === 'workflow' && (string) $del->scope_value === $workflowId;
            });
            if ($workflowMatch) {
                return $workflowMatch;
            }

            // 2. Öncelik: Kategoriye özel tanımlanmış vekalet (scope_type: category)
            $categoryMatch = $activeDelegations->first(function ($del) use ($workflowCategories) {
                return $del->scope_type === 'category' && in_array($del->scope_value, $workflowCategories, true);
            });
            if ($categoryMatch) {
                return $categoryMatch;
            }
        }

        // 3. Öncelik: Genel tüm süreçler için tanımlanmış vekalet (scope_type: all veya boş)
        return $activeDelegations->first(function ($del) {
            return empty($del->scope_type) || $del->scope_type === 'all';
        });
    }
}