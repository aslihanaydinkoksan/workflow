<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Ensure Admin role exists and has all permissions (created by seeder normally)
$role = Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Admin']);
$allPermissions = Spatie\Permission\Models\Permission::all();
if ($allPermissions->count() > 0) {
    $role->syncPermissions($allPermissions);
}

$email = 'admin@local.test';
$passwordPlain = 'Admin1234!';

$user = App\Models\User::updateOrCreate(
    ['email' => $email],
    [
        'name' => 'Full Admin',
        'password' => Illuminate\Support\Facades\Hash::make($passwordPlain),
        'email_verified_at' => now(),
    ]
);

if (! $user->hasRole('Admin')) {
    $user->assignRole('Admin');
}

echo "ADMIN_READY\n";
echo "email={$email}\n";
echo "password={$passwordPlain}\n";
echo "roles=" . implode(',', $user->getRoleNames()->toArray()) . "\n";
echo "permissions_count=" . $user->getAllPermissions()->count() . "\n";

