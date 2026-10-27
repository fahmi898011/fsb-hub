<?php

namespace App\Policies;

use App\Models\InformationItem;
use App\Models\User;

class InformationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('information.view');
    }

    public function view(User $user, InformationItem $item): bool
    {
        // Pengelola (admin/qa) boleh melihat semua status
        if ($user->canManageInformation()) {
            return true;
        }

        // Employee HANYA boleh melihat yang sudah dipublikasikan
        return $item->status === 'published';
    }

    public function create(User $user): bool
    {
        return $user->can('information.create');
    }

    public function update(User $user, InformationItem $item): bool
    {
        return $user->can('information.update');
    }

    public function delete(User $user, InformationItem $item): bool
    {
        return $user->can('information.delete');
    }

    public function publish(User $user, InformationItem $item): bool
    {
        return $user->can('information.publish');
    }

    public function archive(User $user, InformationItem $item): bool
    {
        return $user->can('information.archive');
    }

    public function version(User $user, InformationItem $item): bool
    {
        return $user->can('information.version');
    }
}
