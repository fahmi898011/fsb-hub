<?php

namespace App\Policies;

use App\Models\User;

/**
 * Otorisasi Manajemen Pengguna (Admin / Application Management).
 *
 * Permission: user.view, user.create, user.update, user.disable
 * (diseed oleh RoleAndPermissionSeeder).
 *
 * Aturan tambahan (anti-lockout):
 * - Admin tidak boleh menonaktifkan / menghapus akunnya sendiri.
 * - Admin terakhir tidak boleh dinonaktifkan / dihapus / diturunkan rolenya.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('user.view');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('user.view');
    }

    public function create(User $user): bool
    {
        return $user->can('user.create');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('user.update');
    }

    /**
     * Aktif / nonaktifkan akun.
     */
    public function disable(User $user, User $model): bool
    {
        if (! $user->can('user.disable')) {
            return false;
        }

        if ($user->is($model)) {
            return false;
        }

        // Jangan biarkan admin terakhir dinonaktifkan.
        return ! ($model->hasRole('admin') && $this->adminCount() <= 1);
    }

    public function delete(User $user, User $model): bool
    {
        if (! $user->can('user.disable')) {
            return false;
        }

        if ($user->is($model)) {
            return false;
        }

        return ! ($model->hasRole('admin') && $this->adminCount() <= 1);
    }

    private function adminCount(): int
    {
        return User::role('admin')->count();
    }
}
