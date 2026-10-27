<?php

namespace App\Policies;

use App\Models\QaPeriod;
use App\Models\User;

/**
 * Policy untuk periode QA.
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, periode
 */
class QaPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('qa.period.view');
    }

    public function view(User $user, QaPeriod $period): bool
    {
        return $user->can('qa.period.view');
    }

    public function create(User $user): bool
    {
        return $user->can('qa.period.create');
    }

    public function update(User $user, QaPeriod $period): bool
    {
        return $user->can('qa.period.update');
    }

    public function delete(User $user, QaPeriod $period): bool
    {
        return $user->can('qa.period.delete');
    }

    public function restore(User $user, QaPeriod $period): bool
    {
        return $user->can('qa.period.delete');
    }

    public function forceDelete(User $user, QaPeriod $period): bool
    {
        return $user->can('qa.period.delete');
    }
}