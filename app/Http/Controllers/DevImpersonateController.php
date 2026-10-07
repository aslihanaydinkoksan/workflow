<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DevImpersonateController extends Controller
{
    /**
     * Geliştirme ve test ortamında tek tıkla kullanıcı değiştirme (impersonation).
     */
    public function switchUser(Request $request, $id)
    {
        if (!app()->environment('local')) {
            abort(403, 'Bu özellik sadece yerel geliştirme (local) ortamında kullanılabilir.');
        }

        $user = User::with(['roles', 'department'])->findOrFail($id);

        Auth::login($user);
        $request->session()->regenerate();

        $roleNames = $user->roles->pluck('name')->toArray();
        $roleInfo = count($roleNames) > 0 
            ? ' (' . implode(', ', $roleNames) . ')' 
            : ($user->title ? " ({$user->title})" : '');

        $referer = (string) $request->header('referer', '');
        
        // Eğer önceki sayfa admin yetkisi gerektiren bir sayfaysa ve yeni kullanıcının Admin rolü yoksa dashboard'a yönlendir
        $requiresAdmin = str_contains($referer, '/admin') 
            || str_contains($referer, '/hierarchy') 
            || str_contains($referer, '/form-templates/create')
            || str_contains($referer, '/workflow-designer');

        if ($request->has('redirect_to')) {
            return redirect($request->get('redirect_to'))->with('info', "👤 Aktif kullanıcı değiştirildi: {$user->name}{$roleInfo}");
        }

        if ($requiresAdmin && !$user->hasRole('Admin')) {
            return redirect()->route('dashboard')->with('info', "👤 Aktif kullanıcı değiştirildi: {$user->name}{$roleInfo}");
        }

        return back()->with('info', "👤 Aktif kullanıcı değiştirildi: {$user->name}{$roleInfo}");
    }
}
