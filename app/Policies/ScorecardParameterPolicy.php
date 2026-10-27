<?php

namespace App\Policies;

use App\Models\ScorecardParameter;
use App\Models\User;

/**
 * Otorisasi Parameter Scorecard (PRD Employee KPI/QA §8/§35).
 *
 * AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master
 *
 * Inilah keputusan peran yang paling penting modul ini: yang boleh mengubah
 * BOBOT dan aturan penilaian adalah Admin saja. QA hanya mengisi jawaban
 * (YES/NO/NA) pada assessment, TIDAK boleh menyentuh master ini - sebab
 * kewenangan menilai (QA) harus terpisah dari kewenangan menetapkan aturan
 * penilaian (Admin). Pemisahan ini juga melindungi integritas skor historis.
 */
class ScorecardParameterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('scorecard.view');
    }

    public function view(User $user, ScorecardParameter $parameter): bool
    {
        return $user->can('scorecard.view');
    }

    public function create(User $user): bool
    {
        return $user->can('scorecard.update');
    }

    public function update(User $user, ScorecardParameter $parameter): bool
    {
        return $user->can('scorecard.update');
    }

    public function delete(User $user, ScorecardParameter $parameter): bool
    {
        return $user->can('scorecard.update');
    }
}
