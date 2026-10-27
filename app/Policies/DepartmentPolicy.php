<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

/**
 * Otorisasi Master Divisi (struktur organisasi).
 *
 * Divisi bukan hak akses, jadi yang mengatur hanyalah pemegang
 * `department.*` (praktisnya Admin). Aturan anti-lockout: divisi yang masih
 * punya user atau tim tidak boleh dihapus — sama seperti pola pada Role.
 */
class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('department.view');
    }

    public function view(User $user, Department $department): bool
    {
        return $user->can('department.view');
    }

    public function create(User $user): bool
    {
        return $user->can('department.create');
    }

    public function update(User $user, Department $department): bool
    {
        return $user->can('department.update');
    }

    public function delete(User $user, Department $department): bool
    {
        if (! $user->can('department.delete')) {
            return false;
        }

        // Jangan biarkan struktur organisasi rusak: masih dipakai = tidak boleh hapus.
        return ! $department->users()->exists() && ! $department->teams()->exists();
    }
}
