<?php

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$dbConnection = config('database.default');
$dbDatabase = config("database.connections.$dbConnection.database");

echo "DB_CONNECTION={$dbConnection}\n";
echo "DB_DATABASE={$dbDatabase}\n\n";

$user = App\Models\User::where('email', 'admin@example.com')->first();
if (! $user) {
    echo "User not found: admin@example.com\n";
    exit(1);
}

echo "Found user id={$user->id}, email={$user->email}\n";
echo "password_hash_prefix=" . substr((string) $user->password, 0, 4) . "\n";

echo "Hash::check(Admin1234!) = ";
echo Illuminate\Support\Facades\Hash::check('Admin1234!', $user->password) ? "true\n" : "false\n";

