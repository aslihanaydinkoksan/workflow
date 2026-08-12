<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/**
 * Dev ortamında varsayılan olarak local auth (Breeze) kullanıyoruz.
 * Merkezi SSO ile çalıştırmak için .env içine USE_CENTRAL_SSO=true ekleyebilirsin.
 */
$useCentralSso = filter_var(env('USE_CENTRAL_SSO', false), FILTER_VALIDATE_BOOL);

if ($useCentralSso) {
    // Merkezi SSO Yönlendirmesi
    Route::get('/login', function (\Illuminate\Http\Request $request) {
        $centralSsoUrl = rtrim(env('CENTRAL_SSO_URL', 'http://localhost:8001'), '/');
        $appCode = env('CENTRAL_SSO_APP_CODE', 'workflow-app');
        $callbackUrl = route('sso.login');

        return redirect($centralSsoUrl . '/sso-entry?app_code=' . $appCode . '&redirect_url=' . urlencode($callbackUrl));
    })->name('login');

    Route::post('/logout', function (\Illuminate\Http\Request $request) {
        $centralSsoUrl = rtrim(env('CENTRAL_SSO_URL', 'http://localhost:8001'), '/');
        \Illuminate\Support\Facades\Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect($centralSsoUrl . '/global-logout');
    })->name('logout');

    // SSO Geri Dönüş Rotası
    Route::get('/sso/login', [\App\Http\Controllers\Auth\SsoController::class, 'login'])->name('sso.login');
} else {
    require __DIR__ . '/auth.php';
}

Route::get('/', function () {
    if (\Illuminate\Support\Facades\Auth::check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard')->middleware(['auth', 'verified']);

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    // Dinamik Form Şablonları (Sadece 'create_forms' yetkisi olanlar)
    Route::middleware('can:create_forms')->group(function () {
        Route::post('/form-templates/{form_template}/toggle-status', [\App\Http\Controllers\FormTemplateController::class, 'toggleStatus'])->name('form-templates.toggle-status');
        Route::resource('form-templates', \App\Http\Controllers\FormTemplateController::class);
    });

    // Süreç Akış Tasarımı (Sadece 'manage_workflows' yetkisi olanlar)
    Route::middleware('can:manage_workflows')->group(function () {
        Route::resource('workflows', App\Http\Controllers\WorkflowController::class);
    });

    // Süreç Başlatma ve İzleme (Sadece 'start_processes' yetkisi olanlar)
    Route::middleware('can:start_processes')->group(function () {
        Route::get('/processes', [\App\Http\Controllers\ProcessController::class, 'index'])->name('processes.index');
        Route::get('/processes/create/{workflow}', [\App\Http\Controllers\ProcessController::class, 'create'])->name('processes.create');
        Route::post('/processes/{workflow}', [\App\Http\Controllers\ProcessController::class, 'store'])->name('processes.store');
        Route::get('/processes/history', [\App\Http\Controllers\ProcessController::class, 'history'])->name('processes.history');
        Route::get('/processes/department', [\App\Http\Controllers\ProcessController::class, 'department'])->name('processes.department');
        Route::get('/processes/{instance}/tracker', [\App\Http\Controllers\ProcessController::class, 'tracker'])->name('processes.tracker');
    });

    Route::middleware('can:processes.cancel')->group(function () {
        Route::post('/processes/{instance}/cancel', [\App\Http\Controllers\ProcessController::class, 'cancel'])->name('processes.cancel');
    });

    // Görevlerim (Tüm kullanıcılar kendi görevlerini görebilmeli)
    Route::get('/tasks', [\App\Http\Controllers\TaskController::class, 'index'])->name('tasks.index');
    Route::get('/tasks/{task}', [\App\Http\Controllers\TaskController::class, 'show'])->name('tasks.show');
    Route::post('/tasks/{task}', [\App\Http\Controllers\TaskController::class, 'update'])->name('tasks.update');
    Route::post('/tasks/{task}/undo', [\App\Http\Controllers\TaskController::class, 'undo'])->name('tasks.undo');
    // Vekalet Rotaları
    Route::post('/delegations', [\App\Http\Controllers\DelegationController::class, 'store'])->name('delegations.store');
    Route::delete('/delegations/{delegation}', [\App\Http\Controllers\DelegationController::class, 'destroy'])->name('delegations.destroy');

    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [\App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');

    // Admin Rotaları
    Route::prefix('admin')->name('admin.')->middleware('can:view_admin_panel')->group(function () {
        Route::get('departments/central-list', [\App\Http\Controllers\Admin\DepartmentController::class, 'getCentralDepartments'])->name('departments.central-list')->middleware('can:manage_departments');
        Route::post('departments/sync', [\App\Http\Controllers\Admin\DepartmentController::class, 'syncFromCentral'])->name('departments.sync')->middleware('can:manage_departments');
        Route::resource('departments', \App\Http\Controllers\Admin\DepartmentController::class)->middleware('can:manage_departments');

        Route::get('directorates/central-list', [\App\Http\Controllers\Admin\DirectorateController::class, 'getCentralDirectorates'])->name('directorates.central-list')->middleware('can:manage_departments');
        Route::get('directorates/{directorate}/check-usage', [\App\Http\Controllers\Admin\DirectorateController::class, 'checkUsage'])->name('directorates.check-usage')->middleware('can:manage_departments');
        Route::resource('directorates', \App\Http\Controllers\Admin\DirectorateController::class)->middleware('can:manage_departments');
        Route::get('roles/{role}/check-usage', [\App\Http\Controllers\Admin\RoleController::class, 'checkUsage'])->name('roles.check-usage')->middleware('can:manage_roles');
        Route::resource('roles', \App\Http\Controllers\Admin\RoleController::class)->middleware('can:manage_roles');
        Route::get('users/sync-all-preview', [\App\Http\Controllers\Admin\UserController::class, 'syncAllPreview'])->name('users.sync-all-preview')->middleware('can:manage_users');
        Route::post('users/sync-all-apply', [\App\Http\Controllers\Admin\UserController::class, 'syncAllApply'])->name('users.sync-all-apply')->middleware('can:manage_users');
        Route::get('users/{user}/sync-preview', [\App\Http\Controllers\Admin\UserController::class, 'syncPreview'])->name('users.sync-preview')->middleware('can:manage_users');
        Route::post('users/{user}/sync-apply', [\App\Http\Controllers\Admin\UserController::class, 'syncApply'])->name('users.sync-apply')->middleware('can:manage_users');
        Route::resource('users', \App\Http\Controllers\Admin\UserController::class)->middleware('can:manage_users');
        Route::resource('workflow-categories', \App\Http\Controllers\Admin\WorkflowCategoryController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('can:manage_workflows');

        // Sistem Ayarları
        Route::get('settings', [\App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('settings.index')->middleware('can:view_admin_panel');
        Route::post('settings', [\App\Http\Controllers\Admin\SettingsController::class, 'update'])->name('settings.update')->middleware('can:view_admin_panel');

        // Form Kategorileri
        Route::resource('form-categories', \App\Http\Controllers\Admin\FormCategoryController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('can:create_forms');

        // Hiyerarşi Testi
        Route::get('hierarchy-test', [\App\Http\Controllers\Admin\HierarchyTestController::class, 'index'])->name('hierarchy.test')->middleware('can:view_admin_panel');
        Route::prefix('hierarchy/nodes')->name('hierarchy.nodes.')->group(function () {
            Route::post('/', [\App\Http\Controllers\Admin\HierarchyTestController::class, 'store'])->name('store');
            Route::put('/{node}', [\App\Http\Controllers\Admin\HierarchyTestController::class, 'update'])->name('update');
            Route::delete('/{node}', [\App\Http\Controllers\Admin\HierarchyTestController::class, 'destroy'])->name('destroy');
            Route::patch('/{node}/move', [\App\Http\Controllers\Admin\HierarchyTestController::class, 'move'])->name('move');
            Route::put('hierarchy/tree-types/{treeType}/schema', [\App\Http\Controllers\Admin\HierarchyTestController::class, 'updateSchema'])->name('hierarchy.tree-types.schema.update');
        });
        Route::prefix('tree-types')->name('tree-types.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\TreeTypeController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\Admin\TreeTypeController::class, 'store'])->name('store');
            Route::put('/{treeType}', [\App\Http\Controllers\Admin\TreeTypeController::class, 'update'])->name('update');
            Route::delete('/{treeType}', [\App\Http\Controllers\Admin\TreeTypeController::class, 'destroy'])->name('destroy');
        });

        // Görsel Kural Sihirbazı (Rules Engine)
        Route::prefix('rules')->name('rules.')->group(function () {
            Route::post('/', [\App\Http\Controllers\Admin\RuleController::class, 'store'])->name('store');
            Route::delete('/{rule}', [\App\Http\Controllers\Admin\RuleController::class, 'destroy'])->name('destroy');
            Route::get('/fields/{workflow}', [\App\Http\Controllers\Admin\RuleController::class, 'getAvailableFields'])->name('fields');
            Route::get('/node/{workflow}/{node}', [\App\Http\Controllers\Admin\RuleController::class, 'getRulesByNode']);
            Route::put('/{rule}', [\App\Http\Controllers\Admin\RuleController::class, 'update'])->name('update');
        });
    });
});

// auth.php local auth için yukarıda condition ile dahil ediliyor
