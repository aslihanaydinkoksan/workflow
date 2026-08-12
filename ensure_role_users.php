<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

/**
 * Role adını email local-part'a çevirmek için:
 * - küçük harf
 * - boşluklar: _
 * - Türkçe karakterler dahil: ascii'ye indir
 * - geriye kalan non-alnum/_ karakterleri sil
 */
function role_to_email_local(string $roleName): string
{
    $v = Str::of($roleName)->lower();
    $v = $v->replace([' ', '-'], '_');
    $v = Str::ascii((string) $v);
    $v = preg_replace('/[^a-z0-9_]/', '', (string) $v) ?: 'role';
    return $v;
}

/**
 * Şifre kuralı: <emailLocal> + "_1234!"
 * Örn: admin_1234!
 */
function password_for_email_local(string $emailLocal): string
{
    return $emailLocal . '_1234!';
}

$roles = Spatie\Permission\Models\Role::query()->orderBy('name')->get();
if ($roles->isEmpty()) {
    fwrite(STDERR, "No roles found. Run seeder first.\n");
    exit(1);
}

echo "ROLE_USERS_READY\n";

$hierarchy = [
    'Direktör' => ['title' => 'Direktör', 'department_id' => 2, 'directorate_id' => 1, 'manager_key' => null],
    'Müdür' => ['title' => 'Müdür', 'department_id' => 2, 'directorate_id' => 1, 'manager_key' => 'Direktör'],
    'Amir' => ['title' => 'Amir', 'department_id' => 2, 'directorate_id' => 1, 'manager_key' => 'Müdür'],
    'Kullanıcı' => ['title' => 'Personel', 'department_id' => 2, 'directorate_id' => 1, 'manager_key' => 'Amir'],
    'Müşteri' => ['title' => 'Müşteri', 'department_id' => 2, 'directorate_id' => 1, 'manager_key' => 'Amir'],
    'Mavi Yaka' => ['title' => 'Mavi Yaka', 'department_id' => 2, 'directorate_id' => 1, 'manager_key' => 'Amir'],
    'Admin' => ['title' => 'Sistem Yöneticisi', 'department_id' => 3, 'directorate_id' => 1, 'manager_key' => null],
    'Süreç Tasarımcısı' => ['title' => 'İş Analisti', 'department_id' => 3, 'directorate_id' => 1, 'manager_key' => null],
];

$createdUsers = [];

foreach ($roles as $role) {
    $emailLocal = role_to_email_local($role->name);
    $email = $emailLocal . '@local.test';
    $passwordPlain = password_for_email_local($emailLocal);
    $meta = $hierarchy[$role->name] ?? ['title' => $role->name, 'department_id' => null, 'directorate_id' => null, 'manager_key' => null];

    $user = App\Models\User::updateOrCreate(
        ['email' => $email],
        [
            'name' => $role->name . ' Kullanıcısı',
            'title' => $meta['title'],
            'department_id' => $meta['department_id'],
            'directorate_id' => $meta['directorate_id'],
            'password' => Hash::make($passwordPlain),
            'email_verified_at' => now(),
        ]
    );

    $createdUsers[$role->name] = $user;

    if (! $user->hasRole($role->name)) {
        $user->syncRoles([$role->name]);
    }

    $permCount = $user->getAllPermissions()->count();

    echo "role={$role->name}\n";
    echo "email={$email}\n";
    echo "password={$passwordPlain}\n";
    echo "permissions_count={$permCount}\n";
    echo "---\n";
}

foreach ($roles as $role) {
    $meta = $hierarchy[$role->name] ?? null;
    if (! $meta || empty($meta['manager_key'])) {
        continue;
    }

    $user = $createdUsers[$role->name] ?? null;
    $manager = $createdUsers[$meta['manager_key']] ?? null;

    if ($user && $manager) {
        $user->update(['manager_id' => $manager->id]);
    }
}

// Örnek izin sürecini hiyerarşik onaya çevir
$sampleWorkflow = App\Models\Workflow::where('name', 'Örnek İzin Onay Süreci')->first();
if ($sampleWorkflow) {
    $nodes = $sampleWorkflow->nodes;
    foreach ($nodes as &$node) {
        if (($node['id'] ?? '') === 'approval_node_1') {
            $node['data']['assignType'] = 'hierarchy';
            $node['data']['assignValue'] = 'manager_1';
        }
    }
    unset($node);
    $sampleWorkflow->update(['nodes' => $nodes]);
    echo "WORKFLOW_SAMPLE_UPDATED\n";
}

