<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Department;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use App\Services\MysApiService;
use App\Actions\SyncUsersPreviewAction;
use App\Actions\ApplyUsersSyncAction;
use Exception;

class UserController extends Controller
{
    protected MysApiService $mysApiService;

    // Servisi Dependency Injection ile alıyoruz
    public function __construct(MysApiService $mysApiService)
    {
        $this->mysApiService = $mysApiService;
    }

    public function index(Request $request)
    {
        $search = $request->input('search');
        $departmentIds = $request->input('department_ids', []);
        $roleIds = $request->input('role_ids', []);
        $tab = $request->input('tab', 'users'); // users, customers, maviyaka

        $users = User::with(['department', 'manager', 'roles'])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(!empty($departmentIds), function ($query) use ($departmentIds) {
                $query->whereIn('department_id', $departmentIds);
            })
            ->when(!empty($roleIds), function ($query) use ($roleIds) {
                $query->whereHas('roles', function ($q) use ($roleIds) {
                    $q->whereIn('id', $roleIds);
                });
            })
            ->when($tab === 'users', function ($q) {
                $q->where('is_customer', false)->where('is_mavi_yaka', false);
            })
            ->when($tab === 'customers', function ($q) {
                $q->where('is_customer', true);
            })
            ->when($tab === 'maviyaka', function ($q) {
                $q->where('is_mavi_yaka', true);
            })
            ->orderBy(Department::select('name')->whereColumn('departments.id', 'users.department_id')->limit(1))
            ->orderBy('users.name')
            ->paginate(20)
            ->withQueryString();

        $departments = Department::orderBy('name')->get();
        $roles = Role::orderBy('name')->get();

        return Inertia::render('Admin/Users/Index', [
            'users'       => $users,
            'departments' => $departments,
            'roles'       => $roles,
            'filters'     => [
                'search'         => $search,
                'department_ids' => $departmentIds,
                'role_ids'       => $roleIds,
                'tab'            => $tab
            ]
        ]);
    }

    /**
     * Tüm kullanıcıları MYS'den çekip değişiklikleri önizleme olarak döner.
     */
    public function syncAllPreview(SyncUsersPreviewAction $action)
    {
        try {
            $centralUsers = $this->mysApiService->fetchAllUsers();
            $usersWithChanges = $action->execute($centralUsers);

            return response()->json([
                'success' => true,
                'users'   => $usersWithChanges
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage()
            ]);
        }
    }

    /**
     * Arayüzden seçilen güncellemeleri veritabanına uygular.
     */
    public function syncAllApply(Request $request, ApplyUsersSyncAction $action)
    {
        $validated = $request->validate([
            'updates'               => 'required|array',
            'updates.*.user_id'     => 'required',
            'updates.*.name'        => 'required|string',
            'updates.*.email'       => 'required|email',
            'updates.*.changes'     => 'required|array'
        ]);

        $result = $action->execute($validated['updates']);

        return redirect()->back()->with(
            'success',
            "Toplu senkronizasyon tamamlandı. Toplam {$result['updated']} kullanıcının bilgileri güncellendi, {$result['added']} yeni kullanıcı sisteme eklendi."
        );
    }

    public function edit(User $user)
    {
        $departments = Department::all();
        $roles = Role::all();
        $managers = User::where('id', '!=', $user->id)->get();
        $user->load(['roles', 'department', 'directorate']);

        $userRoleNames = $user->roles->pluck('name')->toArray();

        $centralData = null;
        if ($user->is_customer || $user->is_mavi_yaka) {
            try {
                $centralData = $this->mysApiService->fetchUserDetails($user->email, $user->tc_no);
            } catch (Exception $e) {
                // Sadece logla, arayüzü patlatma
                \Illuminate\Support\Facades\Log::warning("Edit sayfası için MYS verisi çekilemedi: " . $e->getMessage());
            }
        }

        return Inertia::render('Admin/Users/Form', [
            'userModel'     => $user,
            'userRoleNames' => $userRoleNames,
            'departments'   => $departments,
            'roles'         => $roles,
            'managers'      => $managers,
            'centralData'   => $centralData,
        ]);
    }

    public function syncPreview(User $user, SyncUsersPreviewAction $action)
    {
        try {
            $centralData = $this->mysApiService->fetchUserDetails($user->email, $user->tc_no);

            if ($centralData) {
                // Kod tekrarını engellemek için mevcut aksiyonumuzu tek bir kullanıcı için kullanıyoruz
                $changesArray = $action->execute([$centralData]);

                // Eğer dönen dizide bir şey varsa, changes kısmını alıyoruz
                $changes = !empty($changesArray) ? $changesArray[0]['changes'] : [];

                return response()->json([
                    'success'      => true,
                    'changes'      => $changes,
                    'central_data' => $centralData
                ]);
            }

            return response()->json(['success' => false, 'error' => 'Kullanıcı Merkezi Sistemde bulunamadı']);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage()
            ]);
        }
    }

    public function syncApply(Request $request, User $user, ApplyUsersSyncAction $action)
    {
        $changes = $request->input('changes', []);

        // Tekli güncelleme için onaylanmış formatı hazırlıyoruz
        $action->execute([[
            'user_id' => $user->id,
            'name'    => $user->name,
            'email'   => $user->email,
            'changes' => $changes
        ]]);

        return redirect()->back()->with('success', 'Kullanıcı bilgileri Merkezi Sistemden başarıyla güncellendi.');
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'roles' => 'nullable|array'
        ]);

        if (isset($validated['roles'])) {
            $user->syncRoles($validated['roles']);
        } else {
            $user->syncRoles([]);
        }

        return redirect()->route('admin.users.index')->with('success', 'Kullanıcı yetkileri güncellendi.');
    }

    public function destroy(User $user)
    {
        if ($user->id === Auth::id()) {
            return back()->withErrors(['error' => 'Kendi hesabınızı silemezsiniz.']);
        }
        $user->delete();
        return redirect()->route('admin.users.index')->with('success', 'Kullanıcı silindi.');
    }
}
