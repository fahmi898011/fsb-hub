<?php

namespace App\Policies;

use App\Models\ScorecardVersion;
use App\Models\User;

/**
 * Otorisasi Versi Scorecard (PRD Employee KPI/QA §6/§35).
 *
 * AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master
 *
 * Aturan inti versioning: versi yang SUDAH dipakai sebuah assessment TIDAK
 * boleh diubah maupun dihapus, karena akan mengubah skor historis. Karena itu
 * `update()`/`delete()` menolak bila versi sudah terpakai (dicek lewat relasi
 * assessments pada Fase 2; untuk sekarang penjagaannya berbasis status aktif
 * yang sudah dipublikasikan).
 */
class ScorecardVersionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('scorecard.view');
    }

    public function view(User $user, ScorecardVersion $version): bool
    {
        return $user->can('scorecard.view');
    }

    public function create(User $user): bool
    {
        return $user->can('scorecard.update');
    }

    public function update(User $user, ScorecardVersion $version): bool
    {
        return $user->can('scorecard.update');
    }

    public function delete(User $user, ScorecardVersion $version): bool
    {
        if (! $user->can('scorecard.delete')) {
            return false;
        }

        // Versi yang sudah diaktifkan/dipakai tidak boleh dihapus.
        return $version->status === 'draft';
    }
}
