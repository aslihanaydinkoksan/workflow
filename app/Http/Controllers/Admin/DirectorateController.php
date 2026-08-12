<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Directorate;
use App\Models\User;

class DirectorateController extends Controller
{
    public function checkUsage(Directorate $directorate)
    {
        return response()->json([
            'departments_count' => $directorate->departments()->count(),
            'users_count' => \App\Models\User::where('directorate_id', $directorate->id)->count(),
        ]);
    }

    public function index()
    {
        $directorates = Directorate::with('director')->latest()->get();
        $totalDirectorates = Directorate::count();

        return Inertia::render('Admin/Directorates/Index', [
            'directorates' => $directorates,
            'totalDirectorates' => $totalDirectorates,
            'users' => \App\Models\User::all()
        ]);
    }

    public function getCentralDirectorates()
    {
        $centralSsoUrl = rtrim(env('CENTRAL_SSO_URL', 'http://localhost:8001'), '/');
        $apiKey = env('CENTRAL_SSO_API_KEY', 'koksan123'); 
        
        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'X-App-Key' => $apiKey
            ])->get($centralSsoUrl . '/api/internal/directorates');

            if ($response->successful()) {
                return response()->json($response->json());
            }
            return response()->json(['success' => false, 'error' => 'API Hatası: ' . $response->status()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'director_id' => 'nullable|exists:users,id',
            'is_active' => 'boolean'
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;

        Directorate::create($validated);

        return redirect()->route('admin.directorates.index')->with('success', 'Direktörlük başarıyla oluşturuldu.');
    }

    public function update(Request $request, Directorate $directorate)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'director_id' => 'nullable|exists:users,id',
            'is_active' => 'boolean'
        ]);

        $directorate->update($validated);

        return redirect()->route('admin.directorates.index')->with('success', 'Direktörlük başarıyla güncellendi.');
    }

    public function destroy(Directorate $directorate)
    {
        // Temizle
        $directorate->departments()->update(['directorate_id' => null]);
        \App\Models\User::where('directorate_id', $directorate->id)->update(['directorate_id' => null]);

        $directorate->delete();
        return redirect()->route('admin.directorates.index')->with('success', 'Direktörlük silindi.');
    }
}
