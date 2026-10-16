<?php

namespace App\Http\Controllers;

use App\Models\DutyCategory;
use App\Models\ProcessInstance;
use App\Models\Delegation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DutyRadarController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if ($user && !$user->can('duty_radar.view') && !$user->hasRole('Admin') && !$user->hasRole('superadmin')) {
            abort(403, 'Canlı Saha & Personel Radarını görüntüleme yetkiniz bulunmamaktadır.');
        }

        $dateFilter = $request->input('date_filter', 'today');
        $customStart = $request->input('start_date');
        $customEnd = $request->input('end_date');
        $selectedCategory = $request->input('category', 'all');
        $search = $request->input('search');

        // Tarih aralığını belirle
        $today = Carbon::today('Europe/Istanbul');
        
        switch ($dateFilter) {
            case 'this_week':
                $filterStart = $today->copy()->startOfWeek();
                $filterEnd = $today->copy()->endOfWeek();
                break;
            case 'this_month':
                $filterStart = $today->copy()->startOfMonth();
                $filterEnd = $today->copy()->endOfMonth();
                break;
            case 'custom':
                $filterStart = !empty($customStart) ? Carbon::parse($customStart)->startOfDay() : $today->copy();
                $filterEnd = !empty($customEnd) ? Carbon::parse($customEnd)->endOfDay() : $today->copy()->endOfDay();
                break;
            case 'today':
            default:
                $filterStart = $today->copy()->startOfDay();
                $filterEnd = $today->copy()->endOfDay();
                break;
        }

        // Tüm aktif dinamik kategorileri çek
        $categories = DutyCategory::active()->ordered()->get();

        // Aktif vekaletleri çek (Kimin yerine kim bakıyor?)
        $activeDelegations = Delegation::with(['delegator', 'delegatee'])
            ->where(function ($q) {
                $q->where('status', 'active')->orWhereNull('status');
            })
            ->where('start_date', '<=', $filterEnd->toDateString())
            ->where('end_date', '>=', $filterStart->toDateString())
            ->get()
            ->keyBy('delegator_id');

        // Dış görev, seyahat veya saha içeren süreç kayıtlarını tara
        $instances = ProcessInstance::with(['starter.department', 'workflow'])
            ->whereIn('status', ['running', 'completed'])
            ->get();

        $duties = [];
        $categoryCounts = [];
        foreach ($categories as $cat) {
            $categoryCounts[$cat->code] = 0;
        }

        foreach ($instances as $instance) {
            $data = (array) ($instance->data ?? []);
            
            // Tarihleri tespit et
            $startStr = $data['baslangic_tarihi'] ?? $data['cikis_tarihi'] ?? null;
            $endStr = $data['bitis_tarihi'] ?? $data['donus_tarihi'] ?? null;

            if (!$startStr) continue;

            try {
                $dutyStart = Carbon::parse($startStr)->startOfDay();
                $dutyEnd = !empty($endStr) ? Carbon::parse($endStr)->endOfDay() : $dutyStart->copy()->endOfDay();
            } catch (\Exception $e) {
                continue;
            }

            // Seçili tarih aralığıyla kesişiyor mu?
            $overlaps = ($dutyStart <= $filterEnd && $dutyEnd >= $filterStart);
            if (!$overlaps) continue;

            // Görevli personeli tespit et
            $person = null;
            if (!empty($data['gorevli_personel_id'])) {
                $person = User::with('department')->find($data['gorevli_personel_id']);
            }
            if (!$person) {
                $person = $instance->starter;
            }
            if (!$person) continue;

            // Kategori eşleştirmesi
            $rawType = mb_strtolower($data['gorev_turu'] ?? $data['duty_category_code'] ?? '', 'UTF-8');
            $matchedCategory = $this->matchDutyCategory($rawType, $categories);

            if ($matchedCategory) {
                $categoryCounts[$matchedCategory->code] = ($categoryCounts[$matchedCategory->code] ?? 0) + 1;
            }

            // Filtreleme (Kategori)
            if ($selectedCategory !== 'all' && (!$matchedCategory || $matchedCategory->code !== $selectedCategory)) {
                continue;
            }

            // Filtreleme (Arama)
            $destinationCompany = $data['gidilecek_firma'] ?? $data['etkinlik_adi'] ?? $data['fuar_adi'] ?? $data['musteri_adi'] ?? '-';
            $location = $data['lokasyon'] ?? $data['gidilecek_sehir'] ?? $data['sehir_ulke'] ?? '-';
            $purpose = $data['gorev_amaci'] ?? $data['amac'] ?? $instance->title ?? '-';

            if (!empty($search)) {
                $s = mb_strtolower($search, 'UTF-8');
                $matches = str_contains(mb_strtolower($person->name, 'UTF-8'), $s)
                    || str_contains(mb_strtolower($destinationCompany, 'UTF-8'), $s)
                    || str_contains(mb_strtolower($location, 'UTF-8'), $s)
                    || str_contains(mb_strtolower($purpose, 'UTF-8'), $s)
                    || str_contains(mb_strtolower($person->department?->name ?? '', 'UTF-8'), $s);

                if (!$matches) continue;
            }

            // Gün hesabı ve durum rozeti
            $todayStart = $today->copy()->startOfDay();
            $todayEnd = $today->copy()->endOfDay();
            $isOngoingToday = ($dutyStart <= $todayEnd && $dutyEnd >= $todayStart);
            $daysLeft = (int) round($todayStart->diffInDays($dutyEnd->copy()->startOfDay(), false));
            
            if ($isOngoingToday) {
                if ($dutyEnd->isSameDay($today)) {
                    $statusText = 'Bugün Son Gün';
                } elseif ($dutyStart->isSameDay($today)) {
                    $statusText = $daysLeft > 0 ? "Bugün Başladı (Kalan: {$daysLeft} Gün)" : "Bugün Sahada";
                } else {
                    $statusText = $daysLeft > 0 ? "Sahada (Kalan: {$daysLeft} Gün)" : "Bugün Sahada";
                }
            } else {
                if ($dutyStart > $todayEnd) {
                    $daysUntil = max(1, (int) round($todayEnd->diffInDays($dutyStart->copy()->startOfDay(), false)));
                    $statusText = "Başlayacak (" . ($daysUntil === 1 ? "Yarın" : "{$daysUntil} Gün Sonra") . ")";
                } else {
                    $statusText = 'Tamamlandı';
                }
            }

            $delegation = $activeDelegations->get($person->id);

            $duties[] = [
                'id' => $instance->id,
                'process_instance_id' => $instance->id,
                'person' => [
                    'id' => $person->id,
                    'name' => $person->name,
                    'email' => $person->email,
                    'title' => $person->title ?? 'Çalışan',
                    'department' => $person->department?->name ?? 'Genel',
                ],
                'category' => $matchedCategory ? [
                    'id' => $matchedCategory->id,
                    'name' => $matchedCategory->name,
                    'code' => $matchedCategory->code,
                    'icon' => $matchedCategory->icon,
                    'color' => $matchedCategory->color,
                ] : [
                    'id' => null,
                    'name' => $data['gorev_turu'] ?? 'Dış Görev',
                    'code' => 'diger',
                    'icon' => '📍',
                    'color' => 'indigo',
                ],
                'destination_company' => $destinationCompany,
                'location' => $location,
                'purpose' => $purpose,
                'start_date' => $dutyStart->format('d.m.Y'),
                'end_date' => $dutyEnd->format('d.m.Y'),
                'is_ongoing_today' => $isOngoingToday,
                'status_badge' => $statusText,
                'transport' => $data['ulasim_tercihi'] ?? null,
                'accommodation' => $data['konaklama_ihtiyaci'] ?? null,
                'delegation' => $delegation ? [
                    'delegate_name' => $delegation->delegatee?->name,
                    'reason' => $delegation->reason ?? 'Dış Görev İzin',
                ] : null,
                'travel_order_url' => route('processes.travel-order', $instance->id),
                'tracker_url' => route('processes.tracker', $instance->id),
            ];
        }

        // İstatistiki özet
        $totalUsers = User::count();
        $totalOnDuty = count($duties);
        $inFactoryCount = max(0, $totalUsers - $totalOnDuty);

        return Inertia::render('DutyRadar/Index', [
            'duties' => array_values($duties),
            'categories' => $categories,
            'categoryCounts' => $categoryCounts,
            'stats' => [
                'total_on_duty' => $totalOnDuty,
                'in_factory' => $inFactoryCount,
                'active_delegations' => $activeDelegations->count(),
            ],
            'filters' => [
                'date_filter' => $dateFilter,
                'start_date' => $customStart,
                'end_date' => $customEnd,
                'category' => $selectedCategory,
                'search' => $search,
            ],
        ]);
    }

    private function matchDutyCategory(string $rawType, $categories): ?DutyCategory
    {
        if (empty($rawType)) {
            return $categories->firstWhere('code', 'diger') ?? $categories->first();
        }

        foreach ($categories as $cat) {
            $catName = mb_strtolower($cat->name, 'UTF-8');
            $catCode = mb_strtolower($cat->code, 'UTF-8');
            
            if ($rawType === $catCode || str_contains($rawType, $catCode) || str_contains($rawType, $catName) || str_contains($catName, $rawType)) {
                return $cat;
            }
        }

        // Akıllı anahtar kelime eşleştirmesi
        if (str_contains($rawType, 'fuar') || str_contains($rawType, 'etkinlik') || str_contains($rawType, 'stand') || str_contains($rawType, 'kongre')) {
            return $categories->firstWhere('code', 'fuar');
        }
        if (str_contains($rawType, 'müşteri') || str_contains($rawType, 'ziyaret') || str_contains($rawType, 'firma')) {
            return $categories->firstWhere('code', 'musteri');
        }
        if (str_contains($rawType, 'servis') || str_contains($rawType, 'montaj') || str_contains($rawType, 'destek') || str_contains($rawType, 'arıza')) {
            return $categories->firstWhere('code', 'servis');
        }
        if (str_contains($rawType, 'transfer') || str_contains($rawType, 'tesis') || str_contains($rawType, 'fabrika')) {
            return $categories->firstWhere('code', 'transfer');
        }
        if (str_contains($rawType, 'denetim') || str_contains($rawType, 'tedarik') || str_contains($rawType, 'kalite')) {
            return $categories->firstWhere('code', 'denetim');
        }
        if (str_contains($rawType, 'eğitim') || str_contains($rawType, 'seminer') || str_contains($rawType, 'kurs')) {
            return $categories->firstWhere('code', 'egitim');
        }
        if (str_contains($rawType, 'bakanlık') || str_contains($rawType, 'resmi') || str_contains($rawType, 'tse')) {
            return $categories->firstWhere('code', 'resmi');
        }

        return $categories->firstWhere('code', 'diger') ?? $categories->first();
    }
}
