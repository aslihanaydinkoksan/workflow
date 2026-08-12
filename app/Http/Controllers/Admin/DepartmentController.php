<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Department;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::with(['directorate.director', 'managers', 'assistantManagers'])
            ->latest()
            ->get();
        $totalDepartments = Department::count();

        $directorates = \App\Models\Directorate::withTrashed()->get()->map(function($dir) {
            if ($dir->trashed()) {
                $dir->name = $dir->name . ' (Silinmiş)';
            }
            return $dir;
        });

        return Inertia::render('Admin/Departments/Index', [
            'departments' => $departments,
            'totalDepartments' => $totalDepartments,
            'directorates' => $directorates,
            'users' => \App\Models\User::all()
        ]);
    }

    public function getCentralDepartments()
    {
        $centralSsoUrl = rtrim(env('CENTRAL_SSO_URL', 'http://localhost:8001'), '/');
        $apiKey = env('CENTRAL_SSO_API_KEY', 'koksan123'); 
        
        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'X-App-Key' => $apiKey
            ])->get($centralSsoUrl . '/api/internal/departments');

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
            'name' => 'required|string|max:255|unique:departments,name',
            'directorate_id' => 'nullable|exists:directorates,id',
            'manager_ids' => 'nullable|array',
            'manager_ids.*' => 'exists:users,id',
            'assistant_manager_ids' => 'nullable|array',
            'assistant_manager_ids.*' => 'exists:users,id',
        ]);

        $department = Department::create([
            'name' => $validated['name'],
            'directorate_id' => $validated['directorate_id'] ?? null,
            'is_active' => true,
        ]);

        $syncData = [];
        if (!empty($validated['manager_ids'])) {
            foreach ($validated['manager_ids'] as $mId) {
                $syncData[$mId] = ['type' => 'manager'];
            }
        }
        if (!empty($validated['assistant_manager_ids'])) {
            foreach ($validated['assistant_manager_ids'] as $aId) {
                $syncData[$aId] = ['type' => 'assistant_manager'];
            }
        }
        $department->allManagers()->sync($syncData);

        return redirect()->route('admin.departments.index')->with('success', 'Departman başarıyla oluşturuldu.');
    }

    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:departments,name,' . $department->id,
            'directorate_id' => 'nullable|exists:directorates,id',
            'manager_ids' => 'nullable|array',
            'manager_ids.*' => 'exists:users,id',
            'assistant_manager_ids' => 'nullable|array',
            'assistant_manager_ids.*' => 'exists:users,id',
        ]);

        $department->update([
            'name' => $validated['name'],
            'directorate_id' => $validated['directorate_id'] ?? null,
        ]);

        $syncData = [];
        if (!empty($validated['manager_ids'])) {
            foreach ($validated['manager_ids'] as $mId) {
                $syncData[$mId] = ['type' => 'manager'];
            }
        }
        if (!empty($validated['assistant_manager_ids'])) {
            foreach ($validated['assistant_manager_ids'] as $aId) {
                // If a user is both a manager and an assistant manager (unlikely but possible), 
                // the sync dictionary will overwrite the type, or we handle it gracefully.
                // Assuming they are distinct:
                $syncData[$aId] = ['type' => 'assistant_manager'];
            }
        }
        
        // Use the generic relation or sync against the pivot directly
        $department->allManagers()->sync($syncData);

        return redirect()->route('admin.departments.index')->with('success', 'Departman başarıyla güncellendi.');
    }

    public function destroy(Department $department)
    {
        if ($department->children()->count() > 0) {
            return back()->withErrors(['error' => 'Bu departmana bağlı alt departmanlar olduğu için silinemez.']);
        }

        $department->delete();
        return redirect()->route('admin.departments.index')->with('success', 'Departman silindi.');
    }

    public function syncFromCentral()
    {
        $centralSsoUrl = rtrim(env('CENTRAL_SSO_URL', 'http://localhost:8001'), '/');
        $apiKey = env('CENTRAL_SSO_API_KEY', 'koksan123'); 
        
        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'X-App-Key' => $apiKey
            ])->get($centralSsoUrl . '/api/internal/departments');

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['success']) && $data['success']) {
                    $departments = $data['departments'];
                    $syncedCount = 0;

                    $findUserMatch = function ($centralUser) {
                        if (!$centralUser || !is_array($centralUser)) return null;
                        $user = null;
                        if (!empty($centralUser['tc_no'])) {
                            $user = \App\Models\User::where('tc_no', $centralUser['tc_no'])->first();
                        }
                        if (!$user && !empty($centralUser['email'])) {
                            $user = \App\Models\User::where('email', $centralUser['email'])->first();
                        }
                        if (!$user && !empty($centralUser['name'])) {
                            $user = \App\Models\User::where('name', $centralUser['name'])->first();
                        }
                        return $user ? $user->id : null;
                    };

                    foreach ($departments as $dept) {
                        // Yöneticileri metin olarak JSON formatında kaydet
                        $mNames = array_map(function($m) { return is_array($m) ? ($m['name'] ?? '') : $m; }, $dept['managers'] ?? []);
                        $aNames = array_map(function($a) { return is_array($a) ? ($a['name'] ?? '') : $a; }, $dept['assistant_managers'] ?? []);
                        
                        $managerData = [
                            'managers' => array_values(array_filter($mNames)),
                            'assistant_managers' => array_values(array_filter($aNames))
                        ];
                        $managerInfo = json_encode($managerData, JSON_UNESCAPED_UNICODE);

                        $directorData = $dept['director'] ?? null;
                        $directorInfo = is_array($directorData) ? ($directorData['name'] ?? null) : $directorData;
                        
                        $directorateId = null;
                        if ($directorData) {
                            $directorUserId = $findUserMatch($directorData);
                            if ($directorUserId) {
                                $directorate = \App\Models\Directorate::where('director_id', $directorUserId)->first();
                                if ($directorate) {
                                    $directorateId = $directorate->id;
                                }
                            }
                        }

                        $department = Department::updateOrCreate(
                            ['name' => $dept['name']],
                            [
                                'central_id' => $dept['id'],
                                'directorate_id' => $directorateId,
                                'manager_info' => $managerInfo,
                                'director_info' => $directorInfo,
                                'is_synced' => true
                            ]
                        );

                        // Eşleşen kullanıcıları pivot tabloya ekle
                        $syncData = [];
                        foreach ($dept['managers'] ?? [] as $m) {
                            if ($id = $findUserMatch($m)) $syncData[$id] = ['type' => 'manager'];
                        }
                        foreach ($dept['assistant_managers'] ?? [] as $a) {
                            if ($id = $findUserMatch($a)) $syncData[$id] = ['type' => 'assistant_manager'];
                        }
                        if (!empty($syncData)) {
                            $department->allManagers()->sync($syncData);
                        }
                        $syncedCount++;
                    }

                    return redirect()->route('admin.departments.index')->with('success', "Başarılı! Merkezden {$syncedCount} departman senkronize edildi.");
                }
            }
            
            return back()->withErrors(['error' => 'Merkezi sistemden veri alınamadı. Yanıt kodu: ' . $response->status()]);
            
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Senkronizasyon hatası: ' . $e->getMessage()]);
        }
    }
}
