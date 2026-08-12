<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TaskVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class SsoController extends Controller
{
    public function login(Request $request)
    {
        $token = $request->query('token');
        $centralUrl = rtrim(env('CENTRAL_SSO_URL', 'http://localhost:8001'), '/');

        if (!$token) {
            return redirect($centralUrl);
        }

        // Merkezi API'ye token'ı doğrulat
        $response = Http::get($centralUrl . '/api/auth/verify-sso-token', [
            'token' => $token
        ]);

        if ($response->failed()) {
            return redirect($centralUrl)->with('error', 'Merkezi oturum doğrulanamadı.');
        }

        $centralUser = $response->json('user');

        // Veritabanımızda (Zaten sync komutu ile güncel kalıyor)
        $localUser = User::where('email', $centralUser['email'])->first();

        if ($localUser) {
            Auth::login($localUser);

            return $this->redirectAfterLogin($localUser);
        }

        // Eğer kullanıcı henüz sync edilmediyse ve ilk defa geliyorsa, 
        // senkronize etmek için anında oluşturabiliriz
        $fullName = trim(($centralUser['first_name'] ?? '') . ' ' . ($centralUser['last_name'] ?? ''));
        if (empty($fullName)) {
            $fullName = $centralUser['email'];
        }

        $localUser = User::create([
            'name' => $fullName,
            'first_name' => $centralUser['first_name'] ?? null,
            'last_name' => $centralUser['last_name'] ?? null,
            'email' => $centralUser['email'],
            'password' => bcrypt(\Illuminate\Support\Str::random(16)), // Şifre kullanılmayacak
        ]);

        Auth::login($localUser);

        return $this->redirectAfterLogin($localUser);
    }

    private function redirectAfterLogin(User $user): RedirectResponse
    {
        $redirect = redirect()->intended(route('dashboard'));

        if ($message = TaskVisibility::pendingNoticeMessageForUser($user)) {
            $redirect->with('pending_tasks_notice', $message);
        }

        return $redirect;
    }
}
