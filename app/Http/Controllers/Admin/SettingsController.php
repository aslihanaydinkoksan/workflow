<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = Setting::pluck('value', 'key')->toArray();
        $defaultSettings = [
            'sample_follow_up_days' => '30',
            'sample_follow_up_max_reminders' => '3',
            'sample_follow_up_reminder_interval_days' => '7',
            'sap_integration_enabled' => '0',
            'sap_api_url' => '',
            'sap_client' => '100',
            'sap_company_code' => '1000',
            'sap_sales_org' => '1000',
        ];
        $mergedSettings = array_merge($defaultSettings, $settings);

        return Inertia::render('Admin/Settings/Index', [
            'settings' => $mergedSettings
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'app_logo' => 'nullable|image|max:2048',
            'sample_follow_up_days' => 'nullable|integer|min:1|max:365',
            'sample_follow_up_max_reminders' => 'nullable|integer|min:1|max:20',
            'sample_follow_up_reminder_interval_days' => 'nullable|integer|min:1|max:90',
            'sap_integration_enabled' => 'nullable',
            'sap_api_url' => 'nullable|string|max:500',
            'sap_client' => 'nullable|string|max:10',
            'sap_company_code' => 'nullable|string|max:20',
            'sap_sales_org' => 'nullable|string|max:20',
        ]);

        if ($request->hasFile('app_logo')) {
            $path = $request->file('app_logo')->store('settings', 'public');
            Setting::set('app_logo', '/storage/' . $path, 'Sistem Genel Logosu');
        }

        $keys = [
            'sample_follow_up_days' => 'Numune Takip Başlangıç Süresi (Gün)',
            'sample_follow_up_max_reminders' => 'Maksimum Hatırlatma Sayısı',
            'sample_follow_up_reminder_interval_days' => 'Hatırlatma Sıklığı (Gün)',
            'sap_integration_enabled' => 'SAP S/4HANA Entegrasyonu Aktif mi?',
            'sap_api_url' => 'SAP S/4HANA API URL',
            'sap_client' => 'SAP Client No',
            'sap_company_code' => 'SAP Şirket Kodu',
            'sap_sales_org' => 'SAP Satış Organizasyonu',
        ];

        foreach ($keys as $key => $description) {
            if ($request->has($key)) {
                $val = $request->input($key);
                if (is_bool($val)) {
                    $val = $val ? '1' : '0';
                }
                Setting::set($key, (string)$val, $description);
            }
        }

        return redirect()->back()->with('success', 'Ayarlar başarıyla güncellendi.');
    }
}
