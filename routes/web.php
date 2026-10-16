<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/**
 * Merkezi SSO Rotaları ve Yapılandırması:
 * Portaldan (MYS) token ile gelen her kullanıcı için /sso/login rotası her zaman aktiftir.
 * Doğrudan /login adresine girildiğinde MYS'ye yönlendirme ise USE_CENTRAL_SSO=true iken devreye girer.
 */
Route::get('/sso/login', [\App\Http\Controllers\Auth\SsoController::class, 'login'])->name('sso.login');

$useCentralSso = filter_var(config('services.mys.use_central_sso', env('USE_CENTRAL_SSO', false)), FILTER_VALIDATE_BOOL);

if ($useCentralSso) {
    // Merkezi SSO Yönlendirmesi
    Route::get('/login', function (\Illuminate\Http\Request $request) {
        $centralSsoUrl = rtrim(config('services.mys.url', env('CENTRAL_SSO_URL', 'http://localhost:8001')), '/');
        $appCode = config('services.mys.app_code', env('CENTRAL_SSO_APP_CODE', 'workflow-app'));
        $callbackUrl = route('sso.login');

        return redirect($centralSsoUrl . '/sso-entry?app_code=' . $appCode . '&redirect_url=' . urlencode($callbackUrl));
    })->name('login');

    Route::post('/logout', function (\Illuminate\Http\Request $request) {
        $centralSsoUrl = rtrim(config('services.mys.url', env('CENTRAL_SSO_URL', 'http://localhost:8001')), '/');
        \Illuminate\Support\Facades\Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect($centralSsoUrl . '/global-logout');
    })->name('logout');
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

    // Süreç Akış Kataloğu & Tasarımı
    Route::get('/workflows', [\App\Http\Controllers\WorkflowController::class, 'index'])->name('workflows.index');
    Route::middleware('can:manage_workflows')->group(function () {
        Route::post('/workflows/{workflow}/toggle-status', [\App\Http\Controllers\WorkflowController::class, 'toggleStatus'])->name('workflows.toggle-status');
        Route::post('/workflows/{workflow}/duplicate', [\App\Http\Controllers\WorkflowController::class, 'duplicate'])->name('workflows.duplicate');
        Route::post('/workflows/{workflow}/publish-version', [\App\Http\Controllers\WorkflowController::class, 'publishVersion'])->name('workflows.publish-version');
        Route::get('/workflows/{workflow}/versions', [\App\Http\Controllers\WorkflowController::class, 'versions'])->name('workflows.versions');
        Route::get('/workflows/{workflow}/migration-schema', [\App\Http\Controllers\WorkflowController::class, 'migrationSchema'])->name('workflows.migration-schema');
        Route::post('/workflows/{workflow}/migrate-instances', [\App\Http\Controllers\WorkflowController::class, 'migrateInstances'])->name('workflows.migrate-instances');
        Route::resource('workflows', \App\Http\Controllers\WorkflowController::class)->except(['index']);
    });

    // Süreç Başlatma (Sadece 'start_processes' yetkisi olanlar)
    Route::middleware('can:start_processes')->group(function () {
        Route::get('/processes', [\App\Http\Controllers\ProcessController::class, 'index'])->name('processes.index');
        Route::get('/processes/start', fn() => redirect()->route('processes.index'))->name('processes.start');
        Route::get('/processes/create/{workflow}', [\App\Http\Controllers\ProcessController::class, 'create'])->name('processes.create');
        Route::post('/processes/{workflow}', [\App\Http\Controllers\ProcessController::class, 'store'])->name('processes.store');
    });

    // Süreç İzleme, Geçmiş, Düzenleme ve İptal (Yetki denetimleri Controller içinde dinamik yapılır)
    Route::get('/processes/history', [\App\Http\Controllers\ProcessController::class, 'history'])->name('processes.history');
    Route::get('/processes/department', [\App\Http\Controllers\ProcessController::class, 'department'])->name('processes.department')->middleware('can:processes.view_department');
    Route::get('/processes/{instance}/tracker', [\App\Http\Controllers\ProcessController::class, 'tracker'])->name('processes.tracker');
    Route::get('/processes/{instance}/edit', [\App\Http\Controllers\ProcessController::class, 'edit'])->name('processes.edit');
    Route::put('/processes/{instance}', [\App\Http\Controllers\ProcessController::class, 'update'])->name('processes.update');
    Route::post('/processes/{instance}/cancel', [\App\Http\Controllers\ProcessController::class, 'cancel'])->name('processes.cancel');
    Route::post('/processes/{instance}/reschedule-termin', [\App\Http\Controllers\ProcessController::class, 'rescheduleTermin'])->name('processes.reschedule-termin');
    Route::get('/processes/{instance}/certificate', [\App\Http\Controllers\ProcessController::class, 'exportCertificate'])->name('processes.certificate');
    Route::get('/processes/{instance}/travel-order', [\App\Http\Controllers\ProcessController::class, 'exportTravelOrder'])->name('processes.travel-order');
    Route::get('/processes/{instance}/maintenance-order', [\App\Http\Controllers\ProcessController::class, 'exportMaintenanceOrder'])->name('processes.maintenance-order');
    Route::get('/processes/{instance}/dof-report', [\App\Http\Controllers\ProcessController::class, 'exportDofReport'])->name('processes.dof-report');
    Route::get('/processes/{instance}/purchase-order', [\App\Http\Controllers\ProcessController::class, 'exportPurchaseOrder'])->name('processes.purchase-order');
    // Yönetici Süreç Müdahale Kokpiti (Fortune-500 Admin Intervention)
    Route::post('/processes/{instance}/admin/reassign', [\App\Http\Controllers\ProcessController::class, 'adminReassignTask'])->name('processes.admin.reassign');
    Route::post('/processes/{instance}/admin/jump-node', [\App\Http\Controllers\ProcessController::class, 'adminJumpNode'])->name('processes.admin.jump-node');
    Route::post('/processes/{instance}/admin/force-progress', [\App\Http\Controllers\ProcessController::class, 'adminForceProgress'])->name('processes.admin.force-progress');

    // Zamanlanmış Takip ve Anket Rotaları (Generic BPM Follow-up)
    Route::get('/follow-ups', [\App\Http\Controllers\FollowUpController::class, 'index'])->name('follow-ups.index');
    Route::get('/follow-ups/{followUp}', [\App\Http\Controllers\FollowUpController::class, 'show'])->name('follow-ups.show');
    Route::post('/follow-ups/{followUp}', [\App\Http\Controllers\FollowUpController::class, 'update'])->name('follow-ups.update');
    Route::post('/follow-ups/{followUp}/retry-sap', [\App\Http\Controllers\FollowUpController::class, 'retrySap'])->name('follow-ups.retry-sap');

    // Görevlerim (Tüm kullanıcılar kendi görevlerini görebilmeli)
    Route::get('/tasks', [\App\Http\Controllers\TaskController::class, 'index'])->name('tasks.index');
    Route::post('/tasks/bulk-action', [\App\Http\Controllers\TaskController::class, 'bulkAction'])->name('tasks.bulk-action');
    Route::post('/tasks/admin-bulk-destroy', [\App\Http\Controllers\TaskController::class, 'adminBulkDestroy'])
        ->name('tasks.admin-bulk-destroy')
        ->middleware('can:tasks.admin_bulk_delete');

    Route::get('/tasks/{task}', [\App\Http\Controllers\TaskController::class, 'show'])->name('tasks.show')->whereNumber('task');
    Route::post('/tasks/{task}', [\App\Http\Controllers\TaskController::class, 'update'])->name('tasks.update')->whereNumber('task');
    Route::post('/tasks/{task}/undo', [\App\Http\Controllers\TaskController::class, 'undo'])->name('tasks.undo')->whereNumber('task');
    
    // YENİ EKLENEN DIŞA AKTARIM (EXPORT) ROTALARI
    Route::get('/tasks/{task}/export/pdf', [\App\Http\Controllers\TaskController::class, 'exportPdf'])->name('tasks.export.pdf')->whereNumber('task');
    Route::get('/tasks/{task}/export/excel', [\App\Http\Controllers\TaskController::class, 'exportExcel'])->name('tasks.export.excel')->whereNumber('task');
    Route::get('/tasks/{task}/export/certificate', [\App\Http\Controllers\TaskController::class, 'exportCertificate'])->name('tasks.export.certificate')->whereNumber('task');

    // Yönetici Görev & Test Süreç Yönetimi (Admin Soft Delete & Edit)
    Route::put('/tasks/{task}/admin-update', [\App\Http\Controllers\TaskController::class, 'adminUpdate'])
        ->name('tasks.admin-update')
        ->whereNumber('task')
        ->middleware('can:tasks.admin_edit');
    Route::delete('/tasks/{task}/admin-destroy', [\App\Http\Controllers\TaskController::class, 'adminDestroy'])
        ->name('tasks.admin-destroy')
        ->whereNumber('task')
        ->middleware('can:tasks.admin_delete');

    // Vekalet Rotaları
    Route::post('/delegations', [\App\Http\Controllers\DelegationController::class, 'store'])->name('delegations.store');
    Route::delete('/delegations/{delegation}', [\App\Http\Controllers\DelegationController::class, 'destroy'])->name('delegations.destroy');

    // Hiyerarşi / Makine API (Form Entegrasyonu)
    Route::get('/api/hierarchy/nodes', [\App\Http\Controllers\Admin\HierarchyTestController::class, 'apiNodes'])->name('api.hierarchy.nodes');

    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [\App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');

    // Canlı Saha & Personel Radarı (Kim Nerede?)
    Route::get('/duty-radar', [\App\Http\Controllers\DutyRadarController::class, 'index'])->name('duty-radar.index')->middleware('can:duty_radar.view');

    // Admin Rotaları
    Route::prefix('admin')->name('admin.')->middleware('can:view_admin_panel')->group(function () {
        Route::get('departments/central-list', [\App\Http\Controllers\Admin\DepartmentController::class, 'getCentralDepartments'])->name('departments.central-list')->middleware('can:manage_departments');
        Route::post('departments/sync', [\App\Http\Controllers\Admin\DepartmentController::class, 'syncFromCentral'])->name('departments.sync')->middleware('can:manage_departments');
        Route::resource('departments', \App\Http\Controllers\Admin\DepartmentController::class)->middleware('can:manage_departments');

        Route::get('directorates/central-list', [\App\Http\Controllers\Admin\DirectorateController::class, 'getCentralDirectorates'])->name('directorates.central-list')->middleware('can:manage_departments');
        Route::get('directorates/{directorate}/check-usage', [\App\Http\Controllers\Admin\DirectorateController::class, 'checkUsage'])->name('directorates.check-usage')->middleware('can:manage_departments');
        Route::resource('directorates', \App\Http\Controllers\Admin\DirectorateController::class)->middleware('can:manage_departments');
        Route::get('roles/{role}/check-usage', [\App\Http\Controllers\Admin\RoleController::class, 'checkUsage'])->name('roles.check-usage')->middleware('can:manage_roles');
        Route::post('permissions', [\App\Http\Controllers\Admin\RoleController::class, 'storePermission'])->name('permissions.store')->middleware('can:manage_roles');
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

        // Dinamik E-Posta & Kural Yönetimi (Mail Rule Engine)
        Route::prefix('mail-settings')->name('mail-settings.')->middleware('can:view_admin_panel')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\MailSettingsController::class, 'index'])->name('index');
            Route::post('/global', [\App\Http\Controllers\Admin\MailSettingsController::class, 'updateGlobalSettings'])->name('global.update');
            Route::post('/events', [\App\Http\Controllers\Admin\MailSettingsController::class, 'storeEvent'])->name('events.store');
            Route::patch('/events/{event}/status', [\App\Http\Controllers\Admin\MailSettingsController::class, 'updateEventStatus'])->name('events.status');
            Route::delete('/events/{event}', [\App\Http\Controllers\Admin\MailSettingsController::class, 'destroyEvent'])->name('events.destroy');
            Route::put('/rules/{rule}', [\App\Http\Controllers\Admin\MailSettingsController::class, 'updateRule'])->name('rules.update');
            Route::put('/templates/{template}', [\App\Http\Controllers\Admin\MailSettingsController::class, 'updateTemplate'])->name('templates.update');
            Route::post('/test-mail', [\App\Http\Controllers\Admin\MailSettingsController::class, 'sendTestMail'])->name('test-mail');
            Route::post('/send-digest', [\App\Http\Controllers\Admin\MailSettingsController::class, 'sendDigestNow'])->name('send-digest');
        });

        // Form Kategorileri
        Route::resource('form-categories', \App\Http\Controllers\Admin\FormCategoryController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('can:create_forms');

        // Dinamik Görev & Saha Kategorileri (Duty Categories CRUD)
        Route::resource('duty-categories', \App\Http\Controllers\Admin\DutyCategoryController::class)->except(['create', 'show', 'edit'])->middleware('can:duty_radar.manage_categories');
        Route::post('duty-categories/{dutyCategory}/toggle', [\App\Http\Controllers\Admin\DutyCategoryController::class, 'toggleActive'])->name('duty-categories.toggle')->middleware('can:duty_radar.manage_categories');

        // Hiyerarşi Yönetimi
        Route::get('hierarchy', [\App\Http\Controllers\Admin\HierarchyTestController::class, 'index'])->name('hierarchy.index')->middleware('can:view_admin_panel');
        Route::get('hierarchy-test', [\App\Http\Controllers\Admin\HierarchyTestController::class, 'index'])->name('hierarchy.test')->middleware('can:view_admin_panel');
        Route::prefix('hierarchy/nodes')->name('hierarchy.nodes.')->middleware('can:view_admin_panel')->group(function () {
            Route::post('/', [\App\Http\Controllers\Admin\HierarchyTestController::class, 'store'])->name('store');
            Route::put('/{node}', [\App\Http\Controllers\Admin\HierarchyTestController::class, 'update'])->name('update');
            Route::delete('/{node}', [\App\Http\Controllers\Admin\HierarchyTestController::class, 'destroy'])->name('destroy');
            Route::patch('/{node}/move', [\App\Http\Controllers\Admin\HierarchyTestController::class, 'move'])->name('move');
        });
        // Ağaç Tipi Şema Güncelleme — ayrı prefix altında (önceden hierarchy/nodes altında hatalıydı)
        Route::put('hierarchy/tree-types/{treeType}/schema', [\App\Http\Controllers\Admin\HierarchyTestController::class, 'updateSchema'])->name('hierarchy.tree-types.schema.update')->middleware('can:view_admin_panel');
        Route::prefix('tree-types')->name('tree-types.')->middleware('can:view_admin_panel')->group(function () {
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
            Route::get('/node/{workflow}/{node}', [\App\Http\Controllers\Admin\RuleController::class, 'getRulesByNode'])->name('node');
            Route::put('/{rule}', [\App\Http\Controllers\Admin\RuleController::class, 'update'])->name('update');
        });

        // Kurumsal Denetim İzi & Güvenlik Kokpiti (Audit Diff & Log Center)
        Route::get('audit-logs', [\App\Http\Controllers\Admin\AuditLogController::class, 'index'])->name('audit-logs.index');
    });

    // Enterprise BI & Raporlama Modülü (Self-Service Analytics)
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [\App\Http\Controllers\ReportController::class, 'index'])->name('index');
        Route::post('/widgets', [\App\Http\Controllers\ReportController::class, 'storeWidget'])->name('widgets.store');
        Route::delete('/widgets/{widget}', [\App\Http\Controllers\ReportController::class, 'destroyWidget'])->name('widgets.destroy');
        Route::post('/widgets/{widget}/toggle-pin', [\App\Http\Controllers\ReportController::class, 'toggleWidgetPin'])->name('widgets.toggle-pin');
        Route::post('/dashboards', [\App\Http\Controllers\ReportController::class, 'storeDashboard'])->name('dashboards.store');
        Route::put('/dashboards/{dashboard}', [\App\Http\Controllers\ReportController::class, 'updateDashboard'])->name('dashboards.update');
        Route::delete('/dashboards/{dashboard}', [\App\Http\Controllers\ReportController::class, 'destroyDashboard'])->name('dashboards.destroy');
        Route::post('/preview', [\App\Http\Controllers\ReportController::class, 'queryPreview'])->name('preview');
        Route::get('/dashboards/{dashboard}/pdf', [\App\Http\Controllers\ReportController::class, 'exportPdf'])->name('export.pdf');
        Route::get('/dashboards/{dashboard}/excel', [\App\Http\Controllers\ReportController::class, 'exportExcel'])->name('export.excel');
        Route::post('/schedules', [\App\Http\Controllers\ReportController::class, 'storeSchedule'])->name('schedules.store');
        Route::post('/schedules/{schedule}/send-now', [\App\Http\Controllers\ReportController::class, 'sendScheduleNow'])->name('schedules.send-now');
    });
});

// Yerel Geliştirme & Test Ortamı İçin Hızlı Kullanıcı Değiştirici (Impersonate)
if (app()->environment('local')) {
    Route::match(['get', 'post'], '/dev/switch-user/{id}', [\App\Http\Controllers\DevImpersonateController::class, 'switchUser'])->name('dev.switch-user');
    Route::match(['get', 'post'], '/dev/impersonate/{id}', [\App\Http\Controllers\DevImpersonateController::class, 'switchUser']);
}

// auth.php local auth için yukarıda condition ile dahil ediliyor
