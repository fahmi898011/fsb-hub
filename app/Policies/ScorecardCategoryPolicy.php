<?php

namespace App\Policies;

use App\Models\ScorecardCategory;
use App\Models\User;

/**
 * Otorisasi Kategori Scorecard (PRD Employee KPI/QA §7/§35).
 *
 * AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master
 *
 * Kategori adalah bagian dari master penilaian (Admin). Menghapus kategori
 * yang masih berisi parameter ditolak agar struktur tidak rusak.
 */
class ScorecardCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('scorecard.view');
    }

    public function view(User $user, ScorecardCategory $category): bool
    {
        return $user->can('scorecard.view');
    }

    public function create(User $user): bool
    {
        return $user->can('scorecard.update');
    }

    public function update(User $user, ScorecardCategory $category): bool
    {
        return $user->can('scorecard.update');
    }

    public function delete(User $user, ScorecardCategory $category): bool
    {
        if (! $user->can('scorecard.update')) {
            return false;
        }

        return ! $category->parameters()->exists();
    }
}
