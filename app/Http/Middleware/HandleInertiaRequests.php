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
        $userData = null;

        if ($user) {
            // Sadece departmanı yüklüyoruz. 'roles' ilişkisini yüklemekten vazgeçtik 
            // çünkü Vue'ya obje dizisi gitmesini istemiyoruz.
            $user->load(['department']); 
            
            // Tüm kullanıcı verilerini (title, first_name vb. dahil) alıyoruz.
            $userData = $user->toArray();
            
            // VUE'NUN ÇÖKMESİNİ ENGELLEYEN SİHİRLİ DOKUNUŞ:
            // Rolleri obje dizisi olarak değil, doğrudan ["Admin"] gibi düz metin dizisi olarak eziyoruz.
            $userData['roles'] = $user->getRoleNames();
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $userData, 
                'roles' => $user ? $user->getRoleNames() : [],
                'permissions' => $user ? $user->getAllPermissions()->pluck('name') : [],
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
