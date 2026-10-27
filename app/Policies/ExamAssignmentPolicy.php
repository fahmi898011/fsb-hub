<?php

namespace App\Policies;

use App\Models\ExamAssignment;
use App\Models\Setting;
use App\Models\User;

/**
 * Policy untuk assignment ujian ke agent.
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian B
 */
class ExamAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('qa.exam.assignment.view');
    }

    public function view(User $user, ExamAssignment $assignment): bool
    {
        if (!$user->can('qa.exam.assignment.view')) {
            return false;
        }
        // QA yang assign/grade, agent yg dikerjakan, admin
        if ($user->hasRole('qa')) {
            return $assignment->assigned_by === $user->id || $assignment->graded_by === $user->id;
        }
        if ($user->hasRole('employee')) {
            return $assignment->agent_id === $user->id;
        }
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('qa.exam.assignment.create');
    }

    public function update(User $user, ExamAssignment $assignment): bool
    {
        if (!$user->can('qa.exam.assignment.update')) {
            return false;
        }
        // Hanya QA yg assign yg bisa update assigned
        return $assignment->status === 'assigned' && $assignment->assigned_by === $user->id;
    }

    public function delete(User $user, ExamAssignment $assignment): bool
    {
        if (!$user->can('qa.exam.assignment.delete')) {
            return false;
        }
        // Hanya QA yg assign yg bisa hapus assigned
        return $assignment->status === 'assigned' && $assignment->assigned_by === $user->id;
    }

    public function start(User $user, ExamAssignment $assignment): bool
    {
        // Agent memulai pengerjaan
        return $assignment->agent_id === $user->id
            && $assignment->status === 'assigned';
    }

    public function submit(User $user, ExamAssignment $assignment): bool
    {
        // Agent submit jawaban
        return $assignment->agent_id === $user->id
            && $assignment->status === 'in_progress';
    }

    public function grade(User $user, ExamAssignment $assignment): bool
    {
        // QA menilai
        if (!$user->can('qa.exam.assignment.grade')) {
            return false;
        }
        return $assignment->status === 'submitted'
            && ($assignment->assigned_by === $user->id || $user->hasRole('admin'));
    }

    public function publish(User $user, ExamAssignment $assignment): bool
    {
        // QA publish hasil
        if (!$user->can('qa.exam.assignment.publish')) {
            return false;
        }
        return $assignment->status === 'graded'
            && ($assignment->assigned_by === $user->id || $user->hasRole('admin'));
    }

    /* ------------------------------------------------------------------ */
    /* Mode kertas (lembar jawaban)                                        */
    /* ------------------------------------------------------------------ */

    /**
     * Mengunggah / mengganti / menghapus lembar jawaban kertas.
     *
     * Hanya tim QA (izin `qa.exam.assignment.update`). Agent sengaja TIDAK
     * diberi hak ini meskipun ia pemilik jawaban: dialah yang sedang dinilai,
     * jadi membiarkannya mengganti scan sendiri sama dengan membuka jalan
     * mengganti jawaban setelah ujian berlangsung.
     *
     * Status dibatasi ke `assigned`/`in_progress`: setelah dikumpulkan, berkas
     * yang dinilai harus tetap seperti saat diterima.
     */
    public function uploadSheet(User $user, ExamAssignment $assignment): bool
    {
        return $user->can('qa.exam.assignment.update')
            && in_array($assignment->status, ['assigned', 'in_progress'], true);
    }

    /**
     * Membaca berkas lembar jawaban.
     *
     * QA (pemegang `qa.exam.assignment.grade`) dan Admin boleh melihat untuk
     * keperluan penilaian/audit. Agent hanya boleh melihat scan-nya sendiri
     * bila saklar `exam.agent_can_view_sheet` dinyalakan — default mati,
     * karena berkas ini memuat tulisan tangan dan data pelanggan.
     */
    public function viewSheet(User $user, ExamAssignment $assignment): bool
    {
        if ($user->can('qa.exam.assignment.grade') || $user->hasRole('admin')) {
            return true;
        }

        return $assignment->agent_id === $user->id
            && Setting::enabled('exam.agent_can_view_sheet', false);
    }

    /**
     * Menandai ujian kertas sudah dikumpulkan (`assigned`/`in_progress` ->
     * `submitted`).
     *
     * Ini pengganti tombol "Selesai" milik agent pada mode kertas. Aturannya
     * sengaja sama dengan `uploadSheet` supaya yang boleh memasukkan berkas
     * juga yang boleh menyatakan berkas itu sudah diterima.
     */
    public function markCollected(User $user, ExamAssignment $assignment): bool
    {
        return $user->can('qa.exam.assignment.update')
            && in_array($assignment->status, ['assigned', 'in_progress'], true);
    }

    public function restore(User $user, ExamAssignment $assignment): bool
    {
        return $user->can('qa.exam.assignment.delete');
    }

    public function forceDelete(User $user, ExamAssignment $assignment): bool
    {
        return $user->can('qa.exam.assignment.delete');
    }
}