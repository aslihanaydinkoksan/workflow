<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class TaskVisibility
{
    public static function queryForUser(User $user): Builder
    {
        $user->loadMissing('roles');

        // Admin veya Superadmin tüm görevleri görebilir, test edebilir ve onaylayabilir
        if ($user->hasRole('Admin') || $user->hasRole('superadmin')) {
            return Task::query();
        }

        $roleIds = $user->roles->pluck('id')->map(fn ($id) => (int) $id)->all();
        $roleNames = $user->roles->pluck('name')->all();

        return Task::query()->where(function (Builder $query) use ($user, $roleIds, $roleNames) {
            // Doğrudan kullanıcıya atanan görev
            $query->where('assigned_to', $user->id);

            // Rol havuzu: yalnızca belirli kişi seçilmemiş görevler
            $query->orWhere(function (Builder $roleQuery) use ($roleIds, $roleNames) {
                $roleQuery->whereNull('assigned_to');

                if (! empty($roleIds) || ! empty($roleNames)) {
                    $roleQuery->where(function (Builder $inner) use ($roleIds, $roleNames) {
                        if (! empty($roleIds)) {
                            $inner->whereIn('assigned_role_id', $roleIds);
                        }

                        if (! empty($roleNames)) {
                            $inner->orWhereIn('assigned_role', $roleNames);
                        }
                    });
                }
            });
        });
    }

    public static function userCanAccessTask(User $user, Task $task): bool
    {
        return self::queryForUser($user)->whereKey($task->id)->exists();
    }

    public static function personalPendingCountForUser(User $user): int
    {
        $user->loadMissing('roles');

        if ($user->hasRole('Admin') || $user->hasRole('superadmin')) {
            return Task::where('status', 'pending')
                ->where('assigned_to', $user->id)
                ->count();
        }

        return self::queryForUser($user)
            ->where('status', 'pending')
            ->count();
    }

    public static function overdueCountForUser(User $user): int
    {
        $user->loadMissing('roles');

        $query = ($user->hasRole('Admin') || $user->hasRole('superadmin'))
            ? Task::query()->where('assigned_to', $user->id)
            : self::queryForUser($user);

        return $query
            ->where('status', 'pending')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->count();
    }

    public static function totalPoolCount(): int
    {
        return Task::where('status', 'pending')->count();
    }

    public static function pendingCountForUser(User $user): int
    {
        return self::personalPendingCountForUser($user);
    }

    public static function pendingNoticeMessageForUser(User $user): ?string
    {
        $count = self::personalPendingCountForUser($user);

        if ($count < 1) {
            return null;
        }

        return $count === 1
            ? 'Bekleyen 1 kişisel göreviniz mevcuttur.'
            : "{$count} adet bekleyen kişisel göreviniz mevcuttur.";
    }
}
