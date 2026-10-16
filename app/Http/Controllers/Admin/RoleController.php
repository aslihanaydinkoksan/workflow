<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PermissionRegistryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    public function __construct(
        protected PermissionRegistryService $registry
    ) {}

    public function index()
    {
        $roles = Role::withCount('permissions')->with('permissions')->orderBy('id', 'asc')->get();
        foreach ($roles as $role) {
            $role->module_badges = $this->registry->getRoleModuleBadges($role);
        }

        $modules = $this->registry->getModuleGroupedPermissions();

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
            'modules' => $modules,
        ]);
    }

    public function create()
    {
        $modules = $this->registry->getModuleGroupedPermissions();
        return Inertia::render('Admin/Roles/Form', [
            'modules' => $modules,
            'permissions' => Permission::all(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'nullable|array'
        ]);

        $role = Role::create(['name' => $validated['name']]);
        if (!empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return redirect()->route('admin.roles.index')->with('success', 'Rol başarıyla oluşturuldu.');
    }

    public function edit(Role $role)
    {
        $modules = $this->registry->getModuleGroupedPermissions();
        $rolePermissions = $role->permissions->pluck('name')->toArray();

        return Inertia::render('Admin/Roles/Form', [
            'role' => $role,
            'modules' => $modules,
            'permissions' => Permission::all(),
            'rolePermissions' => $rolePermissions
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $role->id,
            'permissions' => 'nullable|array'
        ]);

        $role->update(['name' => $validated['name']]);
        
        if (isset($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return redirect()->route('admin.roles.index')->with('success', 'Rol güncellendi.');
    }

    public function destroy(Role $role)
    {
        $role->delete();
        return redirect()->route('admin.roles.index')->with('success', 'Rol silindi.');
    }

    public function checkUsage(Role $role)
    {
        $userCount = \Illuminate\Support\Facades\DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->count();

        // Workflows tablosunda JSON alanında role id veya rol isminin arandığı durumlar (approx)
        // Eğer 'assignType':'role' ve assignValue rol ID ise:
        $workflowCount = \App\Models\Workflow::where('definition', 'LIKE', '%"assignType":"role"%')
            ->where(function($q) use ($role) {
                $q->where('definition', 'LIKE', '%"assignValue":'.$role->id.'%')
                  ->orWhere('definition', 'LIKE', '%"assignValue":"'.$role->id.'"%')
                  ->orWhere('definition', 'LIKE', '%"assignValue":"'.$role->name.'"%');
            })->count();

        $taskCount = \App\Models\Task::where('assigned_role_id', $role->id)
            ->orWhere('assigned_role', $role->name)
            ->whereIn('status', ['pending', 'in_progress'])
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'userCount' => $userCount,
                'workflowCount' => $workflowCount,
                'taskCount' => $taskCount
            ]
        ]);
    }

    /**
     * İleriye Dönük Yeni Modül & Yetki Tanımlama (Zero-Code Extensibility)
     */
    public function storePermission(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:permissions,name',
            'label' => 'required|string|max:255',
            'module_key' => 'required|string|max:50',
            'module_title' => 'nullable|string|max:100',
            'module_icon' => 'nullable|string|max:10',
            'description' => 'nullable|string|max:500',
        ]);

        $this->registry->registerPermission(
            $validated['name'],
            $validated['label'],
            $validated['module_key'],
            $validated['description'] ?? null,
            $validated['module_title'] ?? null,
            $validated['module_icon'] ?? null
        );

        return back()->with('success', "Yeni '{$validated['label']}' yetkisi sisteme kaydedildi!");
    }
}
