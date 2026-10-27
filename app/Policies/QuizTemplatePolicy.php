<?php

namespace App\Policies;

use App\Models\QuizTemplate;
use App\Models\User;

/**
 * Otorisasi template quiz (PRD Employee KPI/QA §22/§35).
 *
 * AGENT: asisten | 2026-10-05 | buat berkas baru | modul Quiz, fase 4 quiz
 *
 * Template adalah konfigurasi penilaian, jadi mengikuti pola master: QA
 * menyusun, Admin dapat membacanya untuk audit. Yang menetapkan kebijakan
 * adalah QA, bukan pihak yang dinilai (selaras PRD §35).
 */
class QuizTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('quiz.view');
    }

    public function view(User $user, QuizTemplate $template): bool
    {
        return $user->can('quiz.view');
    }

    public function create(User $user): bool
    {
        return $user->can('quiz.create');
    }

    public function update(User $user, QuizTemplate $template): bool
    {
        return $user->can('quiz.update');
    }

    public function delete(User $user, QuizTemplate $template): bool
    {
        return $user->can('quiz.delete');
    }
}
