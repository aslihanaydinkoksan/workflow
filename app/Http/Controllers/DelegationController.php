<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Delegation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Services\NotificationService;


class DelegationController extends Controller
{
    public function store(Request $request, NotificationService $notificationService)
{
    $validated = $request->validate([
        'delegatee_id' => 'required|exists:users,id',
        'start_date'   => 'required|date|after_or_equal:today',
        'end_date'     => 'required|date|after_or_equal:start_date',
    ]);

    $delegation = Delegation::create([
        'delegator_id' => Auth::id(),
        'delegatee_id' => $validated['delegatee_id'],
        'start_date'   => $validated['start_date'],
        'end_date'     => $validated['end_date'],
        'status'       => 'active',
    ]);

    // GEÇİCİ KONTROL 1: Servis çağrılmadan önce durdur
    // dd("Delegation oluştu, servise gidiliyor...", $delegation->toArray());

    $notificationService->delegationAssigned($delegation);

    return redirect()->back()->with('success', 'Vekalet başarıyla tanımlandı.');
}

    public function destroy(Delegation $delegation)
    {
        // Güvenlik: Sadece vekaleti veren kişi iptal edebilir
        if ($delegation->delegator_id !== Auth::id()) {
            abort(403, 'Bu vekaleti iptal etme yetkiniz yok.');
        }

        // İsteğe bağlı olarak soft delete veya status = inactive yapılabilir
        //$delegation->update(['status' => 'inactive']);
        $delegation->delete(); // Alternatif

        return redirect()->back()->with('success', 'Vekalet iptal edildi.');
    }
}
