<?php

namespace App\Policies;

use App\Models\QuestionBank;
use App\Models\User;

/**
 * Otorisasi bank soal (PRD Employee KPI/QA §21/§35).
 *
 * AGENT: asisten | 2026-10-05 | buat berkas baru | modul Quiz, fase 4 quiz
 *
 * Bank soal adalah aset pengetahuan milik QA. Admin mengatur wadah aplikasi dan
 * tetap dapat membacanya untuk audit, tetapi yang menulis/menyunting soal
 * adalah QA (selaras dengan pemisahan "Admin = wadah, QA = isi").
 */
class QuestionBankPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('question_bank.view');
    }

    public function view(User $user, QuestionBank $question): bool
    {
        return $user->can('question_bank.view');
    }

    public function create(User $user): bool
    {
        return $user->can('question_bank.create');
    }

    public function update(User $user, QuestionBank $question): bool
    {
        return $user->can('question_bank.update');
    }

    public function delete(User $user, QuestionBank $question): bool
    {
        return $user->can('question_bank.delete');
    }
}
