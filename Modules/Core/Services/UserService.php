<?php

namespace Modules\Core\Services;

use Modules\Core\Support\AdminStatsCache;
use Modules\Users\Models\User;

class UserService
{
    public function listUsers(array $filters = [])
    {
        $query = User::query()->with('roles');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

         if (!empty($filters['role'])) {
        $query->role($filters['role']);
    }

     if (!empty($filters['search'])) {
        $query->where(function ($q) use ($filters) {
            $q->where('name', 'like', '%' . $filters['search'] . '%')
              ->orWhere('email', 'like', '%' . $filters['search'] . '%');
        });
    }
         if (!empty($filters['sort'])) {
        switch ($filters['sort']) {
            case 'oldest':
                $query->oldest();
                break;

            case 'name':
                $query->orderBy('name');
                break;

            default:
                $query->latest(); 
        }
    } else {
        $query->latest();
    }

    return $query->paginate(10);
    }

    public function activate(int $userId): User
    {
        $user = User::findOrFail($userId);

        $user->update([
            'status' => 'active',
            'suspension_reason' => null
        ]);

        AdminStatsCache::forgetUserLifecycleStats();

        return $user;
    }

    public function suspend(int $userId, string $reason): User
    {
        $user = User::findOrFail($userId);

        $user->update([
            'status' => 'suspended',
            'suspension_reason' => $reason,
        ]);

        AdminStatsCache::forgetUserLifecycleStats();

        return $user;
    }

    public function delete(int $userId): void
    {
        $user = User::findOrFail($userId);
        $user->delete();
        AdminStatsCache::forgetUserLifecycleStats();
    }
}