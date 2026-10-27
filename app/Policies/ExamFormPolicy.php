<?php

namespace App\Policies;

use App\Models\ExamForm;
use App\Models\User;

/**
 * Policy untuk form ujian QA Assessment.
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian B
 */
class ExamFormPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('qa.exam.form.view');
    }

    public function view(User $user, ExamForm $form): bool
    {
        return $user->can('qa.exam.form.view');
    }

    public function create(User $user): bool
    {
        return $user->can('qa.exam.form.create');
    }

    public function update(User $user, ExamForm $form): bool
    {
        if (! $user->can('qa.exam.form.update')) {
            return false;
        }
        // Hanya pembuat yang bisa update draft
        if ($form->status === 'published') {
            return false;
        }

        return $form->created_by === $user->id;
    }

    public function delete(User $user, ExamForm $form): bool
    {
        if (! $user->can('qa.exam.form.delete')) {
            return false;
        }
        // Hanya pembuat yang bisa hapus draft
        if ($form->status === 'published') {
            return false;
        }

        return $form->created_by === $user->id;
    }

    public function publish(User $user, ExamForm $form): bool
    {
        if (! $user->can('qa.exam.form.publish')) {
            return false;
        }

        // Hanya pembuat yang bisa publish draft
        return $form->status === 'draft' && $form->created_by === $user->id;
    }

    /**
     * Tarik form terbit kembali ke draft.
     *
     * Tanpa jalur ini, form yang sudah terbit tetapi soalnya kurang tidak bisa
     * diperbaiki sama sekali (update/delete juga ditolak untuk form terbit).
     * Aman ditarik selama belum ada peserta yang mulai mengerjakan: penugasan
     * berstatus `assigned` belum membuka soal apa pun.
     *
     * AGENT: asisten | 2026-10-20 | tambah jalur tarik ke draft | modul QA Assessment
     */
    public function unpublish(User $user, ExamForm $form): bool
    {
        if (! $user->can('qa.exam.form.publish')) {
            return false;
        }

        if ($form->status !== 'published' || $form->created_by !== $user->id) {
            return false;
        }

        return ! $form->assignments()
            ->whereIn('status', ['in_progress', 'submitted', 'graded', 'published'])
            ->exists();
    }

    public function restore(User $user, ExamForm $form): bool
    {
        return $user->can('qa.exam.form.delete');
    }

    public function forceDelete(User $user, ExamForm $form): bool
    {
        return $user->can('qa.exam.form.delete');
    }
}
