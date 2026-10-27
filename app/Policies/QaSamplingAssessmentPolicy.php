<?php

namespace App\Policies;

use App\Models\QaSamplingAssessment;
use App\Models\User;

/**
 * Otorisasi Assessment QA Sampling (PRD Employee KPI/QA §35/§45).
 *
 * AGENT: asisten | 2026-10-03 | buat berkas baru | modul KPI, fase 2 QA sampling
 *
 * Pembagian wewenang modul ini:
 * - QA   : membuat & mengisi assessment, mengirim (submit) hasilnya.
 * - Admin: mengelola penugasan QA-agent, ikut melihat hasil (read-only atas isi).
 * - Supervisor: memeriksa (review) hasil timnya.
 * - HR   : read-only seluruh hasil.
 * - Karyawan: hanya hasil miliknya sendiri, dan hanya setelah tidak lagi draft.
 *
 * Aturan yang paling penting di sini bukan "siapa boleh membuka halaman",
 * melainkan bahwa isi assessment hanya dapat diubah selama berstatus DRAFT
 * (PRD §16). Sekali dikirim, angkanya sudah jadi dasar KPI dan tidak boleh
 * berubah diam-diam.
 */
class QaSamplingAssessmentPolicy
{
    public function viewAny(User $user): bool
    {
        // Karyawan ikut diizinkan membuka DAFTAR, tetapi query di controller
        // menyaringnya ke baris miliknya sendiri. Tanpa ini, hak "histori
        // penilaian" (PRD §35) tidak akan pernah dapat diakses karyawan.
        return $user->can('qa_sampling.view') || $user->hasRole('employee');
    }

    /**
     * Melihat satu assessment.
     *
     * Karyawan hanya boleh melihat miliknya sendiri (PRD §45: "tidak
     * menampilkan data employee ke user tanpa hak"), dan belum tentu boleh
     * melihat draft-nya sendiri - draft masih milik QA.
     */
    public function view(User $user, QaSamplingAssessment $assessment): bool
    {
        if ($user->can('qa_sampling.view')) {
            return true;
        }

        // Karyawan: hanya data sendiri, dan hanya yang sudah dikirim.
        return $user->id === $assessment->employee_id
            && $user->hasRole('employee')
            && ! $assessment->isEditable();
    }

    public function create(User $user): bool
    {
        return $user->can('qa_sampling.create');
    }

    /**
     * Mengubah isi assessment (jawaban, catatan, sample).
     *
     * Selain izin, status harus DRAFT (PRD §16). Penjagaan status diletakkan di
     * sini juga - bukan hanya di controller - supaya aturannya satu sumber dan
     * tetap berlaku di mana pun policy ini dipanggil.
     *
     * Catatan penting: `isEditable()` (HANYA draft) dipakai, bukan
     * `! isFinal()`. Sebab begitu di-submit, angkanya sudah menjadi dasar KPI
     * karyawan; kalau `submitted`/`reviewed` masih boleh diubah, isi penilaian
     * bisa berubah diam-diam setelah karyawan melihatnya.
     */
    public function update(User $user, QaSamplingAssessment $assessment): bool
    {
        if (! $user->can('qa_sampling.update')) {
            return false;
        }

        return $assessment->isEditable();
    }

    public function delete(User $user, QaSamplingAssessment $assessment): bool
    {
        if (! $user->can('qa_sampling.delete')) {
            return false;
        }

        // DRAFT boleh dibuang; SUBMITTED ke atas adalah bukti penilaian,
        // jangan dihapus - batalkan (cancelled) bila keliru.
        return $assessment->isEditable();
    }

    /**
     * Mengirim assessment ke alur pemeriksaan (draft -> submitted).
     */
    public function submit(User $user, QaSamplingAssessment $assessment): bool
    {
        if (! $user->can('qa_sampling.submit')) {
            return false;
        }

        return $assessment->status === 'draft';
    }

    /**
     * Memeriksa hasil (submitted -> reviewed). Wewenang reviewer/atasan,
     * bukan QA yang menilai.
     */
    public function review(User $user, QaSamplingAssessment $assessment): bool
    {
        if (! $user->can('qa_sampling.review')) {
            return false;
        }

        return $assessment->status === 'submitted';
    }

    /**
     * Mengunci assessment (reviewed -> locked).
     */
    public function lock(User $user, QaSamplingAssessment $assessment): bool
    {
        if (! $user->can('qa_sampling.lock')) {
            return false;
        }

        return $assessment->status === 'reviewed';
    }

    /**
     * Membatalkan assessment (dari status apa pun selain final). Wajib alasan.
     */
    public function cancel(User $user, QaSamplingAssessment $assessment): bool
    {
        if (! $user->can('qa_sampling.cancel')) {
            return false;
        }

        return ! $assessment->isFinal();
    }
}
