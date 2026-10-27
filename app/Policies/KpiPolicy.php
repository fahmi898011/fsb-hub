<?php

namespace App\Policies;

use App\Models\Kpi;
use App\Models\User;

/**
 * Otorisasi Master KPI (PRD Employee KPI/QA §35).
 *
 * AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master
 *
 * Master KPI/Scorecard/Parameter adalah tanggung jawab Admin. QA hanya
 * melihatnya (`kpi.view`) untuk memilih scorecard saat menilai.
 */
class KpiPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('kpi.view');
    }

    public function view(User $user, Kpi $kpi): bool
    {
        return $user->can('kpi.view');
    }

    public function create(User $user): bool
    {
        return $user->can('kpi.create');
    }

    public function update(User $user, Kpi $kpi): bool
    {
        return $user->can('kpi.update');
    }

    public function delete(User $user, Kpi $kpi): bool
    {
        if (! $user->can('kpi.delete')) {
            return false;
        }

        // KPI yang sudah punya scorecard tidak boleh dihapus.
        return ! $kpi->scorecards()->exists();
    }
}
