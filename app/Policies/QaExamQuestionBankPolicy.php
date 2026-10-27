<?php

namespace App\Policies;

use App\Models\QaExamQuestionBank;
use App\Models\User;

/**
 * Policy untuk bank soal ujian QA Assessment.
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian B
 */
class QaExamQuestionBankPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('qa.exam.question-bank.view');
    }

    public function view(User $user, QaExamQuestionBank $question): bool
    {
        return $user->can('qa.exam.question-bank.view');
    }

    public function create(User $user): bool
    {
        return $user->can('qa.exam.question-bank.create');
    }

    public function update(User $user, QaExamQuestionBank $question): bool
    {
        return $user->can('qa.exam.question-bank.update') && $question->created_by === $user->id;
    }

    public function delete(User $user, QaExamQuestionBank $question): bool
    {
        return $user->can('qa.exam.question-bank.delete') && $question->created_by === $user->id;
    }

    public function restore(User $user, QaExamQuestionBank $question): bool
    {
        return $user->can('qa.exam.question-bank.delete');
    }

    public function forceDelete(User $user, QaExamQuestionBank $question): bool
    {
        return $user->can('qa.exam.question-bank.delete');
    }
}