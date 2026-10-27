<?php

namespace App\Policies;

use App\Models\ExamAnswer;
use App\Models\User;

/**
 * Policy untuk jawaban ujian.
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian B
 */
class ExamAnswerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('qa.exam.answer.view');
    }

    public function view(User $user, ExamAnswer $answer): bool
    {
        if (!$user->can('qa.exam.answer.view')) {
            return false;
        }
        $assignment = $answer->assignment;
        if ($user->hasRole('qa')) {
            return $assignment->assigned_by === $user->id || $assignment->graded_by === $user->id;
        }
        if ($user->hasRole('employee')) {
            return $assignment->agent_id === $user->id && $assignment->status === 'published';
        }
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('qa.exam.answer.create');
    }

    public function update(User $user, ExamAnswer $answer): bool
    {
        // Agent update jawaban saat in_progress, QA update score saat grading
        $assignment = $answer->assignment;
        if ($user->hasRole('employee')) {
            return $assignment->agent_id === $user->id
                && $assignment->status === 'in_progress';
        }
        if ($user->hasRole('qa')) {
            return $assignment->status === 'submitted'
                && ($assignment->assigned_by === $user->id || $user->hasRole('admin'));
        }
        return false;
    }

    public function delete(User $user, ExamAnswer $answer): bool
    {
        return $user->can('qa.exam.answer.delete');
    }

    public function restore(User $user, ExamAnswer $answer): bool
    {
        return $user->can('qa.exam.answer.delete');
    }

    public function forceDelete(User $user, ExamAnswer $answer): bool
    {
        return $user->can('qa.exam.answer.delete');
    }
}