<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Department;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;


class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $departmentIds = $request->input('department_ids', []);
        $roleIds = $request->input('role_ids', []);
        $tab = $request->input('tab', 'users'); // users, customers, maviyaka

        $users = User::with(['department', 'manager', 'roles'])
            ->when($search, function ($query, $search) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(!empty($departmentIds), function ($query) use ($departmentIds) {
                $query->whereIn('department_id', $departmentIds);
            })
            ->when(!empty($roleIds), function ($query) use ($roleIds) {
                $query->whereHas('roles', function($q) use ($roleIds) {
                    $q->whereIn('id', $roleIds);
                });
            })
            ->when($tab === 'users', function($q) {
                $q->where('is_customer', false)->where('is_mavi_yaka', false);
            })
            ->when($tab === 'customers', function($q) {
                $q->where('is_customer', true);
            })
            ->when($tab === 'maviyaka', function($q) {
                $q->where('is_mavi_yaka', true);
            })
            ->orderBy(Department::select('name')->whereColumn('departments.id', 'users.department_id')->limit(1))
            ->orderBy('users.name')
            ->paginate(20)
            ->withQueryString();

        $departments = Department::orderBy('name')->get();
        $roles = Role::orderBy('name')->get();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'departments' => $departments,
            'roles' => $roles,
            'filters' => [
                'search' => $search,
                'department_ids' => $departmentIds,
                'role_ids' => $roleIds,
                'tab' => $tab
            ]
        ]);
    }

    public function syncAllPreview(Request $request)
    {
        try {
            $centralSsoUrl = rtrim(env('CENTRAL_SSO_URL', 'http://localhost:8001'), '/');
            $apiKey = env('CENTRAL_SSO_API_KEY', 'koksan123'); 

            // timeout(3) ekledik: KYS kapalıysa 3 saniye sonra pes edip catch bloğuna düşer, siteyi çökertmez.
            $response = \Illuminate\Support\Facades\Http::timeout(3)->withHeaders([
                'X-App-Key' => $apiKey
            ])->get($centralSsoUrl . '/api/internal/users-all');

            if ($response->successful() && $response->json('success')) {
                $centralUsers = $response->json('users');
                $usersWithChanges = [];

                foreach ($centralUsers as $centralUser) {
                    // E-posta veya TC no ile kullanıcıyı bul
                    $user = User::where(function($q) use ($centralUser) {
                        $q->where('email', $centralUser['email']);
                        if (!empty($centralUser['tc_no'])) {
                            $q->orWhere('tc_no', $centralUser['tc_no']);
                        }
                    })->first();
                    
                    if ($user) {
                        $changes = [];
                        
                        if ($user->tc_no !== $centralUser['tc_no']) {
                            $changes['tc_no'] = ['old' => $user->tc_no, 'new' => $centralUser['tc_no']];
                        }
                        if ($user->registration_no !== $centralUser['registration_no']) {
                            $changes['registration_no'] = ['old' => $user->registration_no, 'new' => $centralUser['registration_no']];
                        }
                        if ($user->title !== $centralUser['job_title']) {
                            $changes['title'] = ['old' => $user->title, 'new' => $centralUser['job_title']];
                        }
                        if ((bool)$user->is_customer !== (bool)$centralUser['is_customer']) {
                            $changes['is_customer'] = ['old' => $user->is_customer ? 'Evet' : 'Hayır', 'new' => $centralUser['is_customer'] ? 'Evet' : 'Hayır', 'new_val' => $centralUser['is_customer']];
                        }
                        if ((bool)$user->is_mavi_yaka !== (bool)$centralUser['is_mavi_yaka']) {
                            $changes['is_mavi_yaka'] = ['old' => $user->is_mavi_yaka ? 'Evet' : 'Hayır', 'new' => $centralUser['is_mavi_yaka'] ? 'Evet' : 'Hayır', 'new_val' => $centralUser['is_mavi_yaka']];
                        }
                        
                        // Departman eşleştirme
                        $newDeptId = null;
                        if ($centralUser['department']) {
                            $matchedDept = Department::where('name', $centralUser['department']['name'])->first();
                            if ($matchedDept) $newDeptId = $matchedDept->id;
                        }
                        if ($user->department_id !== $newDeptId) {
                            $oldDept = $user->department ? $user->department->name : 'Yok';
                            $newDept = $newDeptId ? Department::find($newDeptId)->name : 'Yok';
                            $changes['department_id'] = ['old' => $oldDept, 'new' => $newDept, 'new_id' => $newDeptId];
                        }

                        if (!empty($changes)) {
                            $usersWithChanges[] = [
                                'user_id' => $user->id,
                                'name' => $user->name,
                                'email' => $user->email,
                                'changes' => $changes
                            ];
                        }
                    } else {
                        // YENİ KULLANICI EKLENECEK
                        $newDeptId = null;
                        if ($centralUser['department']) {
                            $matchedDept = Department::where('name', $centralUser['department']['name'])->first();
                            if ($matchedDept) $newDeptId = $matchedDept->id;
                        }

                        $usersWithChanges[] = [
                            'user_id' => 'new_' . md5($centralUser['email']),
                            'name' => $centralUser['name'],
                            'email' => $centralUser['email'],
                            'changes' => [
                                'email' => ['old' => 'Yok (Sisteme Yeni Eklenecek)', 'new' => $centralUser['email']],
                                'tc_no' => ['old' => 'Yok', 'new' => $centralUser['tc_no']],
                                'registration_no' => ['old' => 'Yok', 'new' => $centralUser['registration_no']],
                                'title' => ['old' => 'Yok', 'new' => $centralUser['job_title']],
                                'is_customer' => ['old' => 'Yok', 'new' => $centralUser['is_customer'] ? 'Evet' : 'Hayır', 'new_val' => $centralUser['is_customer']],
                                'is_mavi_yaka' => ['old' => 'Yok', 'new' => $centralUser['is_mavi_yaka'] ? 'Evet' : 'Hayır', 'new_val' => $centralUser['is_mavi_yaka']],
                                'department_id' => ['old' => 'Yok', 'new' => $newDeptId ? Department::find($newDeptId)->name : 'Yok', 'new_id' => $newDeptId]
                            ]
                        ];
                    }
                }

                return response()->json([
                    'success' => true,
                    'users' => $usersWithChanges
                ]);
            }

            return response()->json(['success' => false, 'error' => 'Merkezi Sistemle bağlantı kurulamadı veya API hatası.']);
            
        } catch (\Exception $e) {
            // Kodun çökmesini (500) engelleyip arayüze 200 kodlu temiz bir hata mesajı dönüyoruz
            return response()->json([
                'success' => false, 
                'error' => 'KÖKSAN Merkezi Yönetim Sistemi (Port 8001) şu anda kapalı veya ulaşılamıyor.'
            ]);
        }
    }

    public function syncAllApply(Request $request)
    {
        $validated = $request->validate([
            'updates' => 'required|array',
            'updates.*.user_id' => 'required',
            'updates.*.name' => 'required|string',
            'updates.*.email' => 'required|email',
            'updates.*.changes' => 'required|array'
        ]);

        $updatedCount = 0;
        $addedCount = 0;
        $dummyPassword = bcrypt(\Illuminate\Support\Str::random(16)); // Calculate once to avoid Max Execution Time error

        foreach ($validated['updates'] as $update) {
            $changes = $update['changes'];

            if (str_starts_with($update['user_id'], 'new_')) {
                // Yeni Kullanıcı Ekle
                User::create([
                    'name' => $update['name'],
                    'email' => $update['email'],
                    'password' => $dummyPassword, // Merkezi SSO kullanıldığı için şifre önemli değil
                    'tc_no' => $changes['tc_no']['new'] ?? null,
                    'registration_no' => $changes['registration_no']['new'] ?? null,
                    'title' => $changes['title']['new'] ?? null,
                    'is_customer' => $changes['is_customer']['new_val'] ?? false,
                    'is_mavi_yaka' => $changes['is_mavi_yaka']['new_val'] ?? false,
                    'department_id' => $changes['department_id']['new_id'] ?? null,
                ]);
                $addedCount++;
            } else {
                $user = User::find($update['user_id']);
                if ($user) {
                    $userUpdates = [];

                    if (array_key_exists('tc_no', $changes)) $userUpdates['tc_no'] = $changes['tc_no']['new'];
                    if (array_key_exists('registration_no', $changes)) $userUpdates['registration_no'] = $changes['registration_no']['new'];
                    if (array_key_exists('title', $changes)) $userUpdates['title'] = $changes['title']['new'];
                    if (array_key_exists('is_customer', $changes)) $userUpdates['is_customer'] = $changes['is_customer']['new_val'];
                    if (array_key_exists('is_mavi_yaka', $changes)) $userUpdates['is_mavi_yaka'] = $changes['is_mavi_yaka']['new_val'];
                    if (array_key_exists('department_id', $changes)) $userUpdates['department_id'] = $changes['department_id']['new_id'];

                    if (!empty($userUpdates)) {
                        $user->update($userUpdates);
                        $updatedCount++;
                    }
                }
            }
        }

        return redirect()->back()->with('success', "Toplu senkronizasyon tamamlandı. Toplam {$updatedCount} kullanıcının bilgileri güncellendi, {$addedCount} yeni kullanıcı sisteme eklendi.");
    }

    // create ve store fonksiyonları merkezi SSO kullanımı sebebiyle kaldırıldı.

    public function edit(User $user)
    {
        $departments = Department::all();
        $roles = Role::all();
        $managers = User::where('id', '!=', $user->id)->get();
        $user->load(['roles', 'department', 'directorate']);

        // Vue tarafında array of string bekliyoruz roller için
        $userRoleNames = $user->roles->pluck('name')->toArray();

        $centralData = null;
        if ($user->is_customer || $user->is_mavi_yaka) {
            $centralSsoUrl = rtrim(env('CENTRAL_SSO_URL', 'http://localhost:8001'), '/');
            $apiKey = env('CENTRAL_SSO_API_KEY', 'koksan123'); 
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'X-App-Key' => $apiKey
            ])->get($centralSsoUrl . '/api/internal/user-details', [
                'email' => $user->email,
                'tc_no' => $user->tc_no
            ]);
            if ($response->successful() && $response->json('success')) {
                $centralData = $response->json('user');
            }
        }

        return Inertia::render('Admin/Users/Form', [
            'userModel' => $user, // variable name user is reserved in inertia props, avoiding conflict
            'userRoleNames' => $userRoleNames,
            'departments' => $departments,
            'roles' => $roles,
            'managers' => $managers,
            'centralData' => $centralData,
        ]);
    }

    public function syncPreview(User $user)
    {
        try {
            $centralSsoUrl = rtrim(env('CENTRAL_SSO_URL', 'http://localhost:8001'), '/');
            $apiKey = env('CENTRAL_SSO_API_KEY', 'koksan123'); 

            $response = \Illuminate\Support\Facades\Http::timeout(3)->withHeaders([
                'X-App-Key' => $apiKey
            ])->get($centralSsoUrl . '/api/internal/user-details', [
                'email' => $user->email,
                'tc_no' => $user->tc_no
            ]);

            if ($response->successful() && $response->json('success')) {
                $centralUser = $response->json('user');
                
                $changes = [];
                
                if ($centralUser['tc_no'] !== $user->tc_no) $changes['tc_no'] = ['old' => $user->tc_no, 'new' => $centralUser['tc_no']];
                if ($centralUser['registration_no'] !== $user->registration_no) $changes['registration_no'] = ['old' => $user->registration_no, 'new' => $centralUser['registration_no']];
                if ($centralUser['job_title'] !== $user->title) $changes['title'] = ['old' => $user->title, 'new' => $centralUser['job_title']];
                if ((bool)$centralUser['is_customer'] !== (bool)$user->is_customer) {
                    $changes['is_customer'] = ['old' => $user->is_customer ? 'Evet' : 'Hayır', 'new' => $centralUser['is_customer'] ? 'Evet' : 'Hayır', 'new_val' => $centralUser['is_customer']];
                }
                if ((bool)$centralUser['is_mavi_yaka'] !== (bool)$user->is_mavi_yaka) {
                    $changes['is_mavi_yaka'] = ['old' => $user->is_mavi_yaka ? 'Evet' : 'Hayır', 'new' => $centralUser['is_mavi_yaka'] ? 'Evet' : 'Hayır', 'new_val' => $centralUser['is_mavi_yaka']];
                }
                
                // Departman Eşleştirme (İsim üzerinden veya null)
                $newDeptId = null;
                if ($centralUser['department']) {
                    $matchedDept = Department::where('name', $centralUser['department']['name'])->first();
                    if ($matchedDept) $newDeptId = $matchedDept->id;
                }
                if ($newDeptId !== $user->department_id) {
                    $oldDept = $user->department ? $user->department->name : 'Yok';
                    $newDept = $newDeptId ? Department::find($newDeptId)->name : 'Yok';
                    $changes['department_id'] = ['old' => $oldDept, 'new' => $newDept, 'new_id' => $newDeptId];
                }

                return response()->json([
                    'success' => true,
                    'changes' => $changes,
                    'central_data' => $centralUser
                ]);
            }

            return response()->json(['success' => false, 'error' => 'Kullanıcı Merkezi Sistemde bulunamadı veya API hatası']);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false, 
                'error' => 'KÖKSAN Merkezi Yönetim Sistemi (Port 8001) şu anda kapalı.'
            ]);
        }
    }

    public function syncApply(Request $request, User $user)
    {
        $changes = $request->input('changes', []);
        
        $updates = [];
        if (array_key_exists('tc_no', $changes)) $updates['tc_no'] = $changes['tc_no']['new'];
        if (array_key_exists('registration_no', $changes)) $updates['registration_no'] = $changes['registration_no']['new'];
        if (array_key_exists('title', $changes)) $updates['title'] = $changes['title']['new'];
        if (array_key_exists('is_customer', $changes)) $updates['is_customer'] = $changes['is_customer']['new_val'];
        if (array_key_exists('is_mavi_yaka', $changes)) $updates['is_mavi_yaka'] = $changes['is_mavi_yaka']['new_val'];
        if (array_key_exists('department_id', $changes)) $updates['department_id'] = $changes['department_id']['new_id'];

        if (!empty($updates)) {
            $user->update($updates);
        }

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
