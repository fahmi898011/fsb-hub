<?php

namespace App\Policies;

use App\Models\QuizAttempt;
use App\Models\User;

/**
 * Otorisasi pengerjaan quiz (PRD Employee KPI/QA §27/§35/§45).
 *
 * AGENT: asisten | 2026-10-05 | buat berkas baru | modul Quiz, fase 4 quiz
 *
 * Attempt adalah milik KARYAWAN yang mengerjakan. Karyawan hanya boleh melihat
 * dan mengerjakan attempt MILIKNYA (PRD §45: "tidak menampilkan data employee
 * ke user tanpa hak"). QA/Supervisor/HR/Admin boleh melihat untuk laporan,
 * tetapi TIDAK boleh mengubah jawaban orang lain.
 */
class QuizAttemptPolicy
{
    public function create(User $user): bool
    {
        return $user->can('quiz.take');
    }

    public function viewAny(User $user): bool
    {
        // Semua karyawan boleh membuka daftar "Quiz Saya"; query di controller
        // menyaringnya ke baris miliknya sendiri.
        return true;
    }

    public function view(User $user, QuizAttempt $attempt): bool
    {
        if ($user->id === $attempt->employee_id) {
            return true;
        }

        if (! $user->can('quiz_result.view')) {
            return false;
        }

        if ($user->hasRole('supervisor')) {
            return $attempt->employee?->team()
                ->where('teams.leader_id', $user->id)
                ->exists() ?? false;
        }

        return true;
    }

    /**
     * Mengerjakan (membuat/menyimpan jawaban) hanya boleh oleh PEMILIK attempt.
     */
    public function take(User $user, QuizAttempt $attempt): bool
    {
        return $user->can('quiz.take')
            && $user->id === $attempt->employee_id
            && ! $attempt->isCompleted();
    }
}
