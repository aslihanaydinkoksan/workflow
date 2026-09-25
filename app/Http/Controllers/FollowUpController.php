<?php

namespace App\Http\Controllers;

use App\Models\FollowUp;
use App\Models\User;
use App\Services\FollowUpService;
use App\Services\SapIntegrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class FollowUpController extends Controller
{
    /**
     * Numune takiplerini listeler.
     */
    public function index(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $isAdmin = $user->hasRole('Admin') || $user->hasRole('superadmin');

        $query = FollowUp::with([
            'processInstance.workflow',
            'processInstance.starter',
            'assignedUser',
            'respondedByUser'
        ])->latest();

        // Admin veya yönetici değilse sadece kendine atanan veya başlattığı takipler
        if (!$isAdmin) {
            $query->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhereHas('processInstance', function ($sub) use ($user) {
                      $sub->where('started_by', $user->id);
                  });
            });
        }

        // Filtreleme: Durum
        if ($status = $request->input('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        // Arama (Müşteri adı, ürün, sipariş no)
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhere('sap_sales_order_id', 'like', "%{$search}%")
                  ->orWhereHas('processInstance', function ($sub) use ($search) {
                      $sub->where('id', 'like', "%{$search}%")
                          ->orWhere('data->musteri_adi', 'like', "%{$search}%")
                          ->orWhere('data->customer_name', 'like', "%{$search}%")
                          ->orWhere('data->urun_adi', 'like', "%{$search}%");
                  });
            });
        }

        $followUps = $query->paginate(15)->withQueryString();

        // Özet İstatistikler
        $statsBase = $isAdmin ? FollowUp::query() : FollowUp::where('assigned_to', $user->id);
        $stats = [
            'total'         => (clone $statsBase)->count(),
            'pending'       => (clone $statsBase)->where('status', 'pending')->count(),
            'converted'     => (clone $statsBase)->where('status', 'converted')->count(),
            'not_converted' => (clone $statsBase)->where('status', 'not_converted')->count(),
        ];
        $stats['conversion_rate'] = $stats['total'] > 0 
            ? round(($stats['converted'] / max(1, ($stats['converted'] + $stats['not_converted']))) * 100, 1) 
            : 0;

        return Inertia::render('FollowUp/Index', [
            'followUps' => $followUps,
            'filters'   => $request->only(['status', 'search']),
            'stats'     => $stats,
        ]);
    }

    /**
     * Takip detay ve geri bildirim/cevaplama ekranı.
     */
    public function show(FollowUp $followUp)
    {
        /** @var User $user */
        $user = Auth::user();
        $isAdmin = $user->hasRole('Admin') || $user->hasRole('superadmin');

        $isAssigned = $isAdmin 
            || $followUp->assigned_to === $user->id 
            || $followUp->processInstance?->started_by === $user->id;

        if (!$isAssigned) {
            abort(403, 'Bu numune takibine erişim yetkiniz bulunmamaktadır.');
        }

        $followUp->load([
            'processInstance.workflow',
            'processInstance.starter',
            'assignedUser',
            'respondedByUser',
            'subForm',
        ]);

        $reasons = [
            'Fiyat Yüksek Bulundu',
            'Rakip Firma Tercih Edildi',
            'Kalite / Teknik Spesifikasyon Uymadı',
            'Müşteri Projeden / Satın Almadan Vazgeçti',
            'Termin Süresi Uzun Bulundu',
            'Numune Beklenen Performansı Göstermedi',
            'Dosya Teslimi / İade Gecikti',
            'Diğer',
        ];

        return Inertia::render('FollowUp/Show', [
            'followUp' => $followUp,
            'reasons'  => $reasons,
        ]);
    }

    /**
     * Satış temsilcisinin veya ilgilinin cevabını kaydeder.
     */
    public function update(Request $request, FollowUp $followUp, FollowUpService $service)
    {
        /** @var User $user */
        $user = Auth::user();
        $isAdmin = $user->hasRole('Admin') || $user->hasRole('superadmin');

        $isAssigned = $isAdmin 
            || $followUp->assigned_to === $user->id 
            || $followUp->processInstance?->started_by === $user->id;

        if (!$isAssigned) {
            abort(403, 'Bu işlemi yapmaya yetkiniz bulunmamaktadır.');
        }

        $validated = $request->validate([
            'response_status'       => 'required|in:converted,not_converted,rescheduled',
            'order_number'          => 'nullable|required_if:response_status,converted|string|max:100',
            'non_conversion_reason' => 'nullable|required_if:response_status,not_converted|string|max:255',
            'customer_feedback'     => 'nullable|string|max:2000',
            'new_date'              => 'nullable|required_if:response_status,rescheduled|date|after:today',
            'sub_form_answers'      => 'nullable|array',
        ]);

        if (!empty($validated['sub_form_answers'])) {
            $subAnswers = $validated['sub_form_answers'];
            $meta = (array) ($followUp->metadata ?? []);
            $meta['sub_form_answers'] = $subAnswers;
            $followUp->metadata = $meta;
            $followUp->save();

            if ($followUp->processInstance) {
                $merged = array_merge((array) $followUp->processInstance->data, $subAnswers);
                $followUp->processInstance->update(['data' => $merged]);
            }
        }

        $result = $service->recordResponse($followUp, $validated, $user);

        return redirect()->route('follow-ups.index')->with('success', $result['message']);
    }

    /**
     * SAP S/4HANA senkronizasyonunu yeniden dener.
     */
    public function retrySap(FollowUp $followUp, SapIntegrationService $sapService)
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user->hasRole('Admin') && !$user->hasRole('superadmin')) {
            abort(403, 'Bu işlem için yetkiniz bulunmuyor.');
        }

        $result = $sapService->syncSampleOrder($followUp);

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }
}
