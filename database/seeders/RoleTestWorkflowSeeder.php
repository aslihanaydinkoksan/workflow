<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Workflow;
use App\Services\ProcessEngine;
use App\Services\TaskVisibility;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleTestWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@local.test')->first()
            ?? User::where('email', 'admin@test.com')->first();

        if (! $admin) {
            $this->command?->error('Admin kullanıcı bulunamadı.');
            return;
        }

        $engine = app(ProcessEngine::class);
        $roles = Role::orderBy('id')->get();

        // Eski test akışlarını temizle
        Workflow::where('name', 'like', 'TEST ROL:%')->delete();

        $this->command?->info('=== ROL BAZLI TEST SÜREÇLERİ OLUŞTURULUYOR ===');
        $this->command?->newLine();

        foreach ($roles as $role) {
            $workflow = Workflow::create([
                'name' => 'TEST ROL: ' . $role->name,
                'description' => "{$role->name} rolü için otomatik test süreci.",
                'status' => 'active',
                'category' => ['Test'],
                'allowed_departments' => [],
                'allowed_roles' => [],
                'allowed_users' => [],
                'nodes' => [
                    [
                        'id' => 'start',
                        'type' => 'start',
                        'position' => ['x' => 0, 'y' => 80],
                        'data' => ['label' => 'Başlangıç', 'taskType' => 'start'],
                    ],
                    [
                        'id' => 'step_' . $role->id,
                        'type' => 'task',
                        'position' => ['x' => 280, 'y' => 80],
                        'data' => [
                            'label' => $role->name . ' Onayı',
                            'taskType' => 'review',
                            'assignType' => 'role',
                            'assignValue' => (string) $role->id,
                        ],
                    ],
                    [
                        'id' => 'end',
                        'type' => 'end',
                        'position' => ['x' => 560, 'y' => 80],
                        'data' => ['label' => 'Bitiş', 'taskType' => 'end'],
                    ],
                ],
                'edges' => [
                    ['id' => 'e1', 'source' => 'start', 'target' => 'step_' . $role->id],
                    ['id' => 'e2', 'source' => 'step_' . $role->id, 'target' => 'end'],
                ],
                'created_by' => $admin->id,
            ]);

            $instance = $engine->startProcess($workflow, $admin->id, [
                'test_role' => $role->name,
                'created_at' => now()->toIso8601String(),
            ]);

            $pendingCount = $instance->tasks()->where('status', 'pending')->count();
            $assignees = $instance->tasks()
                ->where('status', 'pending')
                ->with('assignedUser')
                ->get()
                ->map(fn ($t) => $t->assignedUser?->email ?? 'rol-havuzu')
                ->unique()
                ->implode(', ');

            $status = $pendingCount > 0 ? 'OK' : 'BOŞ';
            $this->command?->line(sprintf(
                '[%s] %-20s | Süreç #%s | %d görev | %s',
                $status,
                $role->name,
                $instance->id,
                $pendingCount,
                $assignees ?: '(kimseye atanmadı)'
            ));
        }

        $this->command?->newLine();
        $this->command?->info('=== @local.test KULLANICILARI - BEKLEYEN GÖREV ===');
        $this->command?->newLine();

        $usersWithTasks = 0;
        $usersWithoutTasks = 0;

        foreach (User::where('email', 'like', '%@local.test')->orderBy('id')->get() as $user) {
            $user->load('roles');
            $pending = TaskVisibility::queryForUser($user)->where('status', 'pending')->count();
            $roleName = $user->roles->pluck('name')->first() ?? '-';

            if ($pending > 0) {
                $usersWithTasks++;
                $this->command?->line("  ✓ {$user->email} ({$roleName}): {$pending} görev");
            } else {
                $usersWithoutTasks++;
                $this->command?->warn("  ✗ {$user->email} ({$roleName}): görev YOK");
            }
        }

        $this->command?->newLine();
        $this->command?->info("Özet: {$usersWithTasks} kullanıcıda görev var, {$usersWithoutTasks} kullanıcı boşta.");
        $this->command?->info('Giriş: admin@local.test ile Süreç Akışları veya Görevlerim üzerinden kontrol edebilirsiniz.');
    }
}
