<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

/**
 * Otorisasi Master Tim (struktur organisasi).
 *
 * Pemegang `team.view` (mis. supervisor / project leader) boleh melihat tim
 * agar laporan timnya bisa diakses, tetapi hanya pemegang `team.*` penuh
 * (Admin) yang boleh mengubah struktur.
 *
 * Anti-lockout: tim yang masih berisi anggota tidak boleh dihapus.
 */
class TeamPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('team.view');
    }

    public function view(User $user, Team $team): bool
    {
        return $user->can('team.view');
    }

    public function create(User $user): bool
    {
        return $user->can('team.create');
    }

    public function update(User $user, Team $team): bool
    {
        return $user->can('team.update');
    }

    public function delete(User $user, Team $team): bool
    {
        if (! $user->can('team.delete')) {
            return false;
        }

        return ! $team->memberships()->exists();
    }
}
