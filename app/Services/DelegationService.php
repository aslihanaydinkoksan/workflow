<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Delegation;
use Illuminate\Support\Carbon;

class DelegationService
{
    /**
     * Kullanıcının aktif bir vekaleti olup olmadığını kontrol eder.
     * Varsa vekilin (delegatee) ID'sini, yoksa asıl kullanıcının ID'sini döner.
     *
     * @param int $userId Asıl görev atanan kişi (Delegator)
     * @return int Nihai görev atanacak kişi
     */
    public function resolveAssignee(int $userId): int
    {
        $today = Carbon::today()->toDateString();

        $activeDelegation = Delegation::where('delegator_id', $userId)
            ->whereIn('status', ['active', 'approved']) // Sadece onaylanmış veya aktif vekaletler
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->first();

        return $activeDelegation ? (int) $activeDelegation->delegatee_id : $userId;
    }
}