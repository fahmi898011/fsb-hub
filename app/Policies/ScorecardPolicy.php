<?php

namespace App\Policies;

use App\Models\Scorecard;
use App\Models\User;

/**
 * Otorisasi Master Scorecard (PRD Employee KPI/QA §35).
 *
 * AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master
 *
 * Admin penuh; QA/HR/supervisor dapat melihat (`scorecard.view`). Menghapus
 * scorecard yang sudah memiliki versi ditolak agar histori penilaian tidak
 * ikut hilang - pola anti-lockout yang sama seperti Master Divisi/Tim.
 */
class ScorecardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('scorecard.view');
    }

    public function view(User $user, Scorecard $scorecard): bool
    {
        return $user->can('scorecard.view');
    }

    public function create(User $user): bool
    {
        return $user->can('scorecard.create');
    }

    public function update(User $user, Scorecard $scorecard): bool
    {
        return $user->can('scorecard.update');
    }

    public function delete(User $user, Scorecard $scorecard): bool
    {
        if (! $user->can('scorecard.delete')) {
            return false;
        }

        return ! $scorecard->versions()->exists();
    }
}
