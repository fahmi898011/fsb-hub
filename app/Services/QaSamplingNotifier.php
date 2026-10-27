<?php

namespace App\Services;

use App\Models\QaSamplingAssessment;
use App\Models\User;

/**
 * Pemicu notifikasi modul QA Sampling (PRD Employee KPI/QA §35/§49 Fase 6).
 *
 * AGENT: asisten | 2026-10-03 | buat berkas baru | modul KPI, fase 2 QA sampling
 *
 * Dipisah dari NotificationService mengikuti pola TicketNotifier /
 * InformationNotifier: kalimat pesan, audiens, dan tautan terpusat di satu
 * tempat. `type` selalu `qa_sampling` agar ikon di daftar notifikasi konsisten
 * (lihat AppNotification::getIconAttribute()).
 *
 * PRD §49 menempatkan notifikasi di Fase 6, tetapi tiga momen di bawah ini
 * dipasang sekarang karena tanpa pemberitahuan alur "kirim -> periksa ->
 * kunci" mudah macet: QA tidak tahu hasilnya sudah diperiksa, reviewer tidak
 * tahu ada yang menunggu. Sifatnya melengkapi, bukan mengubah alur.
 */
class QaSamplingNotifier
{
    /** Role yang bertindak sebagai pemeriksa hasil. */
    private const REVIEWER_ROLES = ['supervisor', 'project_leader'];

    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * Assessment dikirim QA -> beri tahu pemeriksa.
     */
    public function submitted(QaSamplingAssessment $assessment): void
    {
        $title = 'Assessment QA menunggu pemeriksaan';
        $message = sprintf(
            '%s menilai %s (%s) dengan skor %.2f%%.',
            $assessment->qa?->name ?? 'QA',
            $assessment->employee_name_snapshot ?? 'karyawan',
            $assessment->scorecard_name_snapshot,
            (float) $assessment->final_score,
        );

        $url = route('qa.sampling.show', $assessment);

        foreach (self::REVIEWER_ROLES as $role) {
            $this->notifyRole($role, $title, $message, $url, $assessment->qa_id);
        }
    }

    /**
     * Assessment diperiksa -> beri tahu QA dan karyawan yang dinilai.
     */
    public function reviewed(QaSamplingAssessment $assessment, ?User $actor): void
    {
        $title = 'Assessment QA telah diperiksa';
        $message = sprintf(
            'Penilaian %s (%s) sudah diperiksa. Skor %.2f%%.',
            $assessment->employee_name_snapshot ?? 'karyawan',
            $assessment->scorecard_name_snapshot,
            (float) $assessment->final_score,
        );

        $url = route('qa.sampling.show', $assessment);

        if ($assessment->qa_id && $assessment->qa_id !== $actor?->getKey()) {
            $this->notify($assessment->qa_id, $title, $message, $url);
        }

        if ($assessment->employee_id) {
            $this->notify($assessment->employee_id, $title, $message, $url);
        }
    }

    /**
     * Assessment dikunci -> beri tahu QA dan karyawan (hasil sudah final).
     */
    public function locked(QaSamplingAssessment $assessment): void
    {
        $title = 'Assessment QA dikunci';
        $message = sprintf(
            'Penilaian %s (%s) telah dikunci dengan skor akhir %.2f%%.',
            $assessment->employee_name_snapshot ?? 'karyawan',
            $assessment->scorecard_name_snapshot,
            (float) $assessment->final_score,
        );

        $url = route('qa.sampling.show', $assessment);
        $actorId = auth()->id();

        foreach (array_filter([$assessment->qa_id, $assessment->employee_id]) as $userId) {
            if ($userId !== $actorId) {
                $this->notify($userId, $title, $message, $url);
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                            */
    /* ------------------------------------------------------------------ */

    private function notify(User|int $user, string $title, ?string $message, string $url): void
    {
        $this->notifications->notify($user, $title, $message, 'qa_sampling', $url);
    }

    /**
     * Kirim ke seluruh user AKTIF dengan role tertentu, kecuali pelakunya.
     */
    private function notifyRole(string $role, string $title, ?string $message, string $url, ?int $exceptId): void
    {
        User::role($role)
            ->when($exceptId, fn ($query, $id) => $query->whereKeyNot($id))
            ->get()
            ->filter(fn (User $user) => $user->isActive())
            ->each(fn (User $user) => $this->notify($user, $title, $message, $url));
    }
}
