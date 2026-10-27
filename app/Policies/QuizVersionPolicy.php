<?php

namespace App\Policies;

use App\Models\QuizVersion;
use App\Models\User;

/**
 * Otorisasi versi quiz (PRD Employee KPI/QA §23/§35).
 *
 * AGENT: asisten | 2026-10-05 | buat berkas baru | modul Quiz, fase 4 quiz
 */
class QuizVersionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('quiz.view');
    }

    public function view(User $user, QuizVersion $version): bool
    {
        return $user->can('quiz.view');
    }

    public function create(User $user): bool
    {
        return $user->can('quiz.update');
    }

    /**
     * Menyunting susunan soal sebuah versi.
     *
     * Versi yang sudah dikerjakan karyawan TIDAK boleh diubah lagi: hasil orang
     * yang sudah selesai tidak boleh berubah maknanya (PRD §23).
     */
    public function update(User $user, QuizVersion $version): bool
    {
        return $user->can('quiz.update') && ! $version->isLocked();
    }

    public function publish(User $user, QuizVersion $version): bool
    {
        return $user->can('quiz.publish') && ! $version->isLocked();
    }
}
