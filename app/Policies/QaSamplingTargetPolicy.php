<?php

namespace App\Policies;

use App\Models\QaSamplingTarget;
use App\Models\User;

/**
 * Otorisasi aturan Target Sampling (PRD Employee KPI/QA §17/§18/§35).
 *
 * AGENT: asisten | 2026-10-04 | buat berkas baru | modul KPI, fase 3 QA reporting
 *
 * Target sampling adalah KONFIGURASI KEBIJAKAN, bukan hasil penilaian. Karena
 * itu wewenangnya sama seperti master KPI/Scorecard: Admin yang mengelola,
 * sedangkan QA/HR/Supervisor hanya MEMBACA (mereka perlu tahu targetnya agar
 * angka "Completed 87 dari Target 100" punya arti, tetapi tidak boleh
 * mengubah kebijakan itu sendiri).
 *
 * Alasan pemisahan ini bukan sekadar kerapian: bila QA dapat mengubah target,
 * capaian sampling menjadi angka yang bisa "diatur" oleh pihak yang dinilai
 * capaiannya. Integritas laporan mensyaratkan angka pembanding dipegang
 * pihak lain.
 *
 * NB: permission memakai awalan `qa_sampling_target.` — dan bukan
 * `sampling_target.` — agar tidak bertabrakan dengan modul sampling lain yang
 * mungkin menyusul, sekaligus tetap terbaca sebagai satu keluarga dengan
 * `qa_sampling.*`.
 */
class QaSamplingTargetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('qa_sampling_target.view');
    }

    public function view(User $user, QaSamplingTarget $target): bool
    {
        return $user->can('qa_sampling_target.view');
    }

    public function create(User $user): bool
    {
        return $user->can('qa_sampling_target.create');
    }

    public function update(User $user, QaSamplingTarget $target): bool
    {
        return $user->can('qa_sampling_target.update');
    }

    /**
     * Menghapus aturan target.
     *
     * Aturan yang sudah pernah berlaku (effective_date di masa lalu) sengaja
     * TIDAK boleh dihapus, melainkan dinonaktifkan. Menghapusnya akan membuat
     * laporan periode lampau kehilangan angka pembandingnya, sehingga capaian
     * yang dulu tercatat 87% bisa berubah hanya karena aturannya dihapus
     * (PRD §11/§38: angka historis tidak boleh berubah karena konfigurasi).
     */
    public function delete(User $user, QaSamplingTarget $target): bool
    {
        if (! $user->can('qa_sampling_target.delete')) {
            return false;
        }

        return $target->effective_date === null || $target->effective_date->isFuture();
    }
}
