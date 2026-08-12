<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Workflow;
use App\Models\FormTemplate;
use App\Models\ProcessInstance;
use App\Models\Task;
use App\Services\TaskVisibility;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user()->load('department');

        // Rol ve departman tabanlı basit bir yetki kontrolü
        // Gerçek bir senaryoda Spatie/Permission veya Gate kullanılabilir
        $isAdminOrGM = $user->can('view_admin_panel');

        $stats = [];

        if ($isAdminOrGM) {
            $stats = [
                'role' => 'admin',
                'total_users' => User::count(),
                'total_forms' => FormTemplate::count(),
                'active_workflows' => Workflow::where('status', 'active')->count(),
                'running_processes' => ProcessInstance::where('status', 'running')->count(),
                'completed_processes' => ProcessInstance::where('status', 'completed')->count(),
                'recent_processes' => ProcessInstance::with(['workflow', 'starter'])->latest()->take(5)->get(),
            ];
        } else {
            $taskQuery = TaskVisibility::queryForUser($user);
            $stats = [
                'role' => 'user',
                'pending_tasks' => (clone $taskQuery)->where('status', 'pending')->count(),
                'my_running_processes' => ProcessInstance::where('started_by', $user->id)->where('status', 'running')->count(),
                'my_completed_processes' => ProcessInstance::where('started_by', $user->id)->where('status', 'completed')->count(),
                'recent_tasks' => (clone $taskQuery)
                    ->where('status', 'pending')
                    ->with('processInstance.workflow')
                    ->latest()
                    ->take(5)
                    ->get(),
            ];
        }

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'user' => $user
        ]);
    }
}
