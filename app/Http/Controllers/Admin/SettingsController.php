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
        return Inertia::render('Admin/Settings/Index', [
            'settings' => $settings
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'app_logo' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('app_logo')) {
            $path = $request->file('app_logo')->store('settings', 'public');
            Setting::updateOrCreate(
                ['key' => 'app_logo'],
                ['value' => '/storage/' . $path, 'description' => 'Sistem Genel Logosu']
            );
        }

        return redirect()->back()->with('success', 'Ayarlar başarıyla güncellendi.');
    }
}
