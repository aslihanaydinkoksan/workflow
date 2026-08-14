<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\User;
use App\Services\MysApiService;
use App\Actions\SyncDepartmentsAction;
use App\Actions\SyncDirectoratesAction;
use Exception;

class DepartmentController extends Controller
{
    protected MysApiService $mysApiService;

    public function __construct(MysApiService $mysApiService)
    {
        $this->mysApiService = $mysApiService;
    }

    public function index()
    {
        $departments = Department::with(['directorate.director', 'managers', 'assistantManagers'])
            ->latest()
            ->get();
        $totalDepartments = Department::count();

        $directorates = Directorate::withTrashed()->get()->map(function ($dir) {
            if ($dir->trashed()) {
                $dir->name = $dir->name . ' (Silinmiş)';
            }
            return $dir;
        });

        return Inertia::render('Admin/Departments/Index', [
            'departments'      => $departments,
            'totalDepartments' => $totalDepartments,
            'directorates'     => $directorates,
            'users'            => User::all()
        ]);
    }

    public function getCentralDepartments()
    {
        try {
            $departments = $this->mysApiService->fetchAllDepartments();
            return response()->json(['success' => true, 'departments' => $departments]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                  => 'required|string|max:255|unique:departments,name',
            'directorate_id'        => 'nullable|exists:directorates,id',
            'manager_ids'           => 'nullable|array',
            'manager_ids.*'         => 'exists:users,id',
            'assistant_manager_ids' => 'nullable|array',
            'assistant_manager_ids.*' => 'exists:users,id',
        ]);

        $department = Department::create([
            'name'           => $validated['name'],
            'directorate_id' => $validated['directorate_id'] ?? null,
            'is_active'      => true,
        ]);

        $syncData = [];
        if (!empty($validated['manager_ids'])) {
            foreach ($validated['manager_ids'] as $mId) $syncData[$mId] = ['type' => 'manager'];
        }
        if (!empty($validated['assistant_manager_ids'])) {
            foreach ($validated['assistant_manager_ids'] as $aId) $syncData[$aId] = ['type' => 'assistant_manager'];
        }
        $department->allManagers()->sync($syncData);

        return redirect()->route('admin.departments.index')->with('success', 'Departman başarıyla oluşturuldu.');
    }

    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'name'                  => 'required|string|max:255|unique:departments,name,' . $department->id,
            'directorate_id'        => 'nullable|exists:directorates,id',
            'manager_ids'           => 'nullable|array',
            'manager_ids.*'         => 'exists:users,id',
            'assistant_manager_ids' => 'nullable|array',
            'assistant_manager_ids.*' => 'exists:users,id',
        ]);

        $department->update([
            'name'           => $validated['name'],
            'directorate_id' => $validated['directorate_id'] ?? null,
        ]);

        $syncData = [];
        if (!empty($validated['manager_ids'])) {
            foreach ($validated['manager_ids'] as $mId) $syncData[$mId] = ['type' => 'manager'];
        }
        if (!empty($validated['assistant_manager_ids'])) {
            foreach ($validated['assistant_manager_ids'] as $aId) $syncData[$aId] = ['type' => 'assistant_manager'];
        }

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

    /**
     * Tek Tuşla Hem Direktörlükleri Hem Departmanları Senkronize Eder
     */
    public function syncFromCentral(SyncDirectoratesAction $syncDirAction, SyncDepartmentsAction $syncDeptAction)
    {
        try {
            // 1. Önce Direktörlükleri çekip kaydediyoruz (Bağımlılık sorunu olmaması için)
            $centralDirectorates = $this->mysApiService->fetchAllDirectorates();
            $syncDirAction->execute($centralDirectorates);

            // 2. Ardından Departmanları çekip kaydediyoruz
            $centralDepartments = $this->mysApiService->fetchAllDepartments();
            $result = $syncDeptAction->execute($centralDepartments);

            return redirect()->route('admin.departments.index')->with('success', "Başarılı! Merkezden {$result['synced']} departman (ve tüm direktörlükler) senkronize edildi.");
        } catch (Exception $e) {
            return back()->withErrors(['error' => 'Senkronizasyon hatası: ' . $e->getMessage()]);
        }
    }
}
