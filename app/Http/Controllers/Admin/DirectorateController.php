<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Directorate;
use App\Models\User;
use App\Services\MysApiService;
use Exception;

class DirectorateController extends Controller
{
    protected MysApiService $mysApiService;

    public function __construct(MysApiService $mysApiService)
    {
        $this->mysApiService = $mysApiService;
    }

    public function checkUsage(Directorate $directorate)
    {
        return response()->json([
            'departments_count' => $directorate->departments()->count(),
            'users_count'       => User::where('directorate_id', $directorate->id)->count(),
        ]);
    }

    public function index()
    {
        $directorates = Directorate::with('director')->latest()->get();
        $totalDirectorates = Directorate::count();

        return Inertia::render('Admin/Directorates/Index', [
            'directorates'      => $directorates,
            'totalDirectorates' => $totalDirectorates,
            'users'             => User::all()
        ]);
    }

    public function getCentralDirectorates()
    {
        try {
            $directorates = $this->mysApiService->fetchAllDirectorates();
            return response()->json(['success' => true, 'directorates' => $directorates]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'director_id' => 'nullable|exists:users,id',
            'is_active'   => 'boolean'
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;
        Directorate::create($validated);

        return redirect()->route('admin.directorates.index')->with('success', 'Direktörlük başarıyla oluşturuldu.');
    }

    public function update(Request $request, Directorate $directorate)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'director_id' => 'nullable|exists:users,id',
            'is_active'   => 'boolean'
        ]);

        $directorate->update($validated);
        return redirect()->route('admin.directorates.index')->with('success', 'Direktörlük başarıyla güncellendi.');
    }

    public function destroy(Directorate $directorate)
    {
        $directorate->departments()->update(['directorate_id' => null]);
        User::where('directorate_id', $directorate->id)->update(['directorate_id' => null]);

        $directorate->delete();
        return redirect()->route('admin.directorates.index')->with('success', 'Direktörlük silindi.');
    }
}
