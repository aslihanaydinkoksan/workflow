<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::withCount('permissions')->latest()->get();
        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles
        ]);
    }

    public function create()
    {
        $permissions = Permission::all();
        return Inertia::render('Admin/Roles/Form', [
            'permissions' => $permissions
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
        $permissions = Permission::all();
        $rolePermissions = $role->permissions->pluck('name')->toArray();

        return Inertia::render('Admin/Roles/Form', [
            'role' => $role,
            'permissions' => $permissions,
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
}
