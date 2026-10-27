<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Otorisasi pengelolaan Role & Permission (Admin / Application Management).
 *
 * Permission yang dipakai: user.view / user.create / user.update
 * (Role dikelola sebagai bagian dari Manajemen Pengguna, karena role adalah
 * wadah hak akses, bukan data konten).
 *
 * Aturan anti-lockout:
 * - Role 'admin' tidak boleh kehilangan permission kunci (admin-access /
 *   user.view), supaya admin tidak mengunci dirinya sendiri dari aplikasi.
 * - Role yang masih dipakai user tidak boleh dihapus.
 */
class RolePolicy
{
    /**
     * Permission yang wajib dimiliki role 'admin'.
     *
     * @var list<string>
     */
    public const ADMIN_REQUIRED_PERMISSIONS = ['user.view'];

    public function viewAny(User $user): bool
    {
        return $user->can('user.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can('user.view');
    }

    public function create(User $user): bool
    {
        return $user->can('user.create');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can('user.update');
    }

    public function delete(User $user, Role $role): bool
    {
        if (! $user->can('user.disable')) {
            return false;
        }

        // Role bawaan (yang punya user) tidak boleh dihapus.
        if ($role->users()->exists()) {
            return false;
        }

        // Role fungsional inti tetap dipertahankan agar struktur hak akses
        // aplikasi tidak hilang tanpa sengaja.
        return ! in_array($role->name, User::functionalRoles(), true);
    }

    /**
     * Cek apakah daftar permission baru masih menyisakan izin wajib admin.
     *
     * @param  array<int, string>  $permissions
     */
    public function keepsAdminAccess(Role $role, array $permissions): bool
    {
        if ($role->name !== 'admin') {
            return true;
        }

        foreach (self::ADMIN_REQUIRED_PERMISSIONS as $required) {
            if (! in_array($required, $permissions, true)) {
                return false;
            }
        }

        return true;
    }
}
