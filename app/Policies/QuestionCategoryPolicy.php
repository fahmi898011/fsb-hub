<?php

namespace App\Policies;

use App\Models\QuestionCategory;
use App\Models\User;

/**
 * Otorisasi kategori bank soal (PRD Employee KPI/QA §21/§35).
 *
 * AGENT: asisten | 2026-10-05 | buat berkas baru | modul Quiz, fase 4 quiz
 *
 * Kategori adalah bagian dari bank soal, jadi wewenangnya sama: QA menyusun,
 * Admin dapat membacanya. Memakai izin `question_bank.*` yang sama agar tidak
 * ada izin tambahan yang harus dipelajari pengguna untuk satu keluarga data.
 */
class QuestionCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('question_bank.view');
    }

    public function view(User $user, QuestionCategory $category): bool
    {
        return $user->can('question_bank.view');
    }

    public function create(User $user): bool
    {
        return $user->can('question_bank.create');
    }

    public function update(User $user, QuestionCategory $category): bool
    {
        return $user->can('question_bank.update');
    }

    public function delete(User $user, QuestionCategory $category): bool
    {
        return $user->can('question_bank.delete');
    }
}
