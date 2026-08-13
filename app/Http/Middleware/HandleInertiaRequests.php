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
        // CANLI TESTİ İÇİN GEÇİCİ LOGLAMA:
    \Illuminate\Support\Facades\Log::info('Inertia User Check:', [
        'default_user' => $request->user()?->id,
        'auth_user' => \Illuminate\Support\Facades\Auth::user()?->id,
        'all_session' => session()->all()
    ]);
        $user = $request->user();
        $userData = null;

        if ($user) {
            // Sadece Vue'ya obje olarak gitmesi gereken 'department' ilişkisini yüklüyoruz.
            // Spatie ilişkilerini (roles, permissions) bilerek yüklemiyoruz çünkü 
            // onlar obje dizisi olarak değil, sadece İSİMLERİ (string dizisi) olarak lazım!
            $user->load(['department']); 
            
            // Tüm kullanıcı bilgilerini alıyoruz. (İçinde roller veya yetkiler obje olarak YOK)
            $userData = $user->toArray();
            
            // Vue tarafındaki çökmeleri ve 405 hatalarını önlemek için, 
            // Rolleri ve Yetkileri sadece düz metin (string array) olarak gönderiyoruz.
            $userData['roles'] = $user->getRoleNames()->toArray();
            $userData['permissions'] = $user->getAllPermissions()->pluck('name')->toArray();
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $userData, 
                // Bu aşağıdaki iki satırı aslında yukarıda $userData içine eklediğimiz 
                // için artık tamamen gereksizler ama Vue kodların belki dışarıdan da (props.auth.roles olarak) 
                // okuyordur diye güvenli bir yedek olarak bırakıyoruz.
                'roles' => $user ? $user->getRoleNames()->toArray() : [],
                'permissions' => $user ? $user->getAllPermissions()->pluck('name')->toArray() : [],
            ],
            'centralSsoUrl' => rtrim(env('CENTRAL_SSO_URL', 'http://localhost:8001'), '/'),
            'app_logo' => \App\Models\Setting::where('key', 'app_logo')->value('value'),
            'flash' => [
                'success' => fn() => $request->session()->get('success'),
                'error' => fn() => $request->session()->get('error'),
                'warning' => fn() => $request->session()->get('warning'),
                'info' => fn() => $request->session()->get('info'),
                'pending_tasks_notice' => fn() => $request->session()->get('pending_tasks_notice'),
            ],
            'pending_tasks_count' => fn() => $user
                ? TaskVisibility::queryForUser($user)->where('status', 'pending')->count()
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
        ];
    }
}
