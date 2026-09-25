<?php

namespace App\Http\Controllers;

use App\Models\FollowUp;
use App\Models\FormTemplate;
use App\Models\ProcessInstance;
use App\Models\User;
use App\Models\Workflow;
use App\Services\TaskVisibility;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        /** @var User $user */
        $user = auth()->user()->load('department');
        $isAdminOrGM = $user->can('view_admin_panel') || $user->hasRole('Admin') || $user->hasRole('superadmin');

        // 1. Kullanıcının Kişisel İş Listesi (Tüm departmanlar ve süreçler için ortak!)
        $taskQuery = TaskVisibility::queryForUser($user);
        $pendingTasks = (clone $taskQuery)
            ->where('status', 'pending')
            ->with(['processInstance.workflow', 'processInstance.starter'])
            ->orderBy('due_date')
            ->latest()
            ->take(6)
            ->get();
        $pendingTasksCount = (clone $taskQuery)->where('status', 'pending')->count();

        // 2. Kullanıcının Takipleri (Follow-Ups)
        $followUpQuery = FollowUp::where('status', 'pending')
            ->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhereHas('processInstance', fn($sub) => $sub->where('started_by', $user->id));
            });

        $pendingFollowUps = (clone $followUpQuery)
            ->with(['processInstance.workflow', 'processInstance.starter'])
            ->orderBy('scheduled_at')
            ->take(4)
            ->get();
        $pendingFollowUpsCount = (clone $followUpQuery)->count();

        // 3. Kullanıcının Başlattığı Devam Eden & Son Talepler
        $myRecentProcesses = ProcessInstance::where('started_by', $user->id)
            ->with(['workflow'])
            ->latest()
            ->take(5)
            ->get();
        $myRunningCount = ProcessInstance::where('started_by', $user->id)->where('status', 'running')->count();
        $myCompletedCount = ProcessInstance::where('started_by', $user->id)->where('status', 'completed')->count();

        // 4. Popüler / Hızlı Başlatılabilecek Süreçler (Tüm birimler)
        $quickWorkflows = Workflow::where('status', 'active')
            ->with('formTemplate')
            ->latest()
            ->take(6)
            ->get();

        // 5. Yönetici İstatistikleri (Varsa)
        $adminStats = null;
        if ($isAdminOrGM) {
            $adminStats = [
                'total_users'              => User::count(),
                'active_workflows'         => Workflow::where('status', 'active')->count(),
                'running_processes'        => ProcessInstance::where('status', 'running')->count(),
                'completed_processes'      => ProcessInstance::where('status', 'completed')->count(),
                'total_follow_ups'         => FollowUp::count(),
                'company_recent_processes' => ProcessInstance::with(['workflow', 'starter'])->latest()->take(5)->get(),
            ];
        }

        $stats = [
            'is_admin'                 => $isAdminOrGM,
            'pending_tasks_count'      => $pendingTasksCount,
            'pending_tasks'            => $pendingTasks,
            'pending_follow_ups_count' => $pendingFollowUpsCount,
            'pending_follow_ups'       => $pendingFollowUps,
            'my_running_count'         => $myRunningCount,
            'my_completed_count'       => $myCompletedCount,
            'my_recent_processes'      => $myRecentProcesses,
            'quick_workflows'          => $quickWorkflows,
            'admin_stats'              => $adminStats,
        ];

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'user'  => $user
        ]);
    }
}
