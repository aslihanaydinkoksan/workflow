<?php

namespace App\Http\Middleware;

use App\Models\UserNotification;
use App\Services\TaskVisibility;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        // Rol ve Yetkileri düz metin (string array) olarak hazırlıyoruz.
        // Array_values ile indexleri sıfırlayarak JSON'da obje gibi algılanmasını önlüyoruz.
        $roles = $user ? array_values($user->getRoleNames()->toArray()) : [];
        $permissions = $user ? array_values($user->getAllPermissions()->pluck('name')->toArray()) : [];

        // Kullanıcı verisini güvenli hale getiriyoruz. (Spatie objelerinden arındırıyoruz)
        $userData = $user ? [
            'id' => $user->id,
            'name' => $user->name ?? 'Kullanıcı',
            'first_name' => $user->first_name ?? '',
            'last_name' => $user->last_name ?? '',
            'title' => $user->title ?? '',
            'department_id' => $user->department_id,
            'email' => $user->email,
            'roles' => $roles, // String dizisi (örn: ["Admin"])
            'permissions' => $permissions // String dizisi (örn: ["view_admin_panel"])
        ] : null;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $userData,
                'roles' => $roles,
                'permissions' => $permissions,
            ],
            'centralSsoUrl' => rtrim(env('CENTRAL_SSO_URL', 'http://localhost:8001'), '/'),
            'app_logo' => \App\Models\Setting::where('key', 'app_logo')->value('value') ?: asset('logo.jpg'),
            'flash' => [
                'success' => fn() => $request->session()->get('success'),
                'error' => fn() => $request->session()->get('error'),
                'warning' => fn() => $request->session()->get('warning'),
                'info' => fn() => $request->session()->get('info'),
                'pending_tasks_notice' => fn() => $request->session()->get('pending_tasks_notice'),
            ],
            'pending_tasks_count' => fn() => $user
                ? TaskVisibility::personalPendingCountForUser($user)
                : 0,
            'total_pool_tasks_count' => fn() => $user && ($user->hasRole('Admin') || $user->hasRole('superadmin'))
                ? TaskVisibility::totalPoolCount()
                : 0,
            'overdue_tasks_count' => fn() => $user
                ? TaskVisibility::overdueCountForUser($user)
                : 0,
            'is_admin_user' => fn() => $user
                ? ($user->hasRole('Admin') || $user->hasRole('superadmin'))
                : false,
            'pending_follow_ups_count' => fn() => $user
                ? ($user->hasRole('Admin') || $user->hasRole('superadmin')
                    ? \App\Models\FollowUp::where('status', 'pending')->count()
                    : \App\Models\FollowUp::where('assigned_to', $user->id)->where('status', 'pending')->count())
                : 0,
            'my_running_processes_count' => fn() => $user
                ? \App\Models\ProcessInstance::where('started_by', $user->id)->where('status', 'running')->count()
                : 0,
            'unread_notifications_count' => fn() => $user
                ? UserNotification::query()
                ->where('user_id', $user->id)
                ->whereNull('read_at')
                ->count()
                : 0,
            'recent_notifications' => fn() => $user
                ? UserNotification::query()
                ->where('user_id', $user->id)
                ->latest()
                ->limit(8)
                ->get(['id', 'type', 'title', 'body', 'data', 'read_at', 'created_at'])
                : [],
            'dev_mode' => app()->environment('local'),
            'dev_users' => fn () => app()->environment('local') ? \App\Models\User::with(['roles:id,name', 'department:id,name'])
                ->select('id', 'name', 'first_name', 'last_name', 'email', 'title', 'department_id')
                ->get()
                ->map(function ($u) {
                    return [
                        'id' => $u->id,
                        'name' => $u->name,
                        'email' => $u->email,
                        'title' => $u->title,
                        'department' => $u->department?->name,
                        'department_id' => $u->department_id,
                        'roles' => $u->roles->pluck('name')->toArray(),
                    ];
                }) : [],
            'dev_departments' => fn () => app()->environment('local') ? \App\Models\Department::select('id', 'name')->orderBy('name')->get() : [],
            'dev_roles' => fn () => app()->environment('local') ? \Spatie\Permission\Models\Role::select('id', 'name')->orderBy('name')->get() : [],
        ];
    }
}
