<?php

namespace App\Services;

use App\Models\ExamAssignment;
use App\Models\QaSamplingAssessment;
use App\Models\Ticket;
use App\Models\User;

/**
 * Tugas yang menunggu user, disusun untuk blok "Tugas saya" di Beranda.
 *
 * Sebelum ini Beranda identik untuk semua peran: agent tidak pernah melihat
 * ujian yang menunggu, QA tidak melihat antrean penilaian, dan IT tidak
 * melihat tiket yang ditugaskan kepadanya. Satu-satunya cara mengetahui
 * pekerjaan yang menunggu adalah membuka menu satu per satu.
 *
 * Tugas disaring dengan IZIN yang benar-benar dimiliki user (bukan hanya
 * nama peran), supaya setiap tautan yang dirender pasti bisa dibuka. Tugas
 * berjumlah nol tidak dikembalikan: baris "0" bukan informasi, ia hanya
 * menambah derau di halaman yang seharusnya ringkas.
 */
class HomeTaskService
{
    /** Batas jumlah baris agar blok tetap bisa dipindai dalam satu lihatan. */
    private const MAX_TASKS = 4;

    /**
     * @return list<array{key: string, label: string, hint: string, count: int, url: string, icon: string}>
     */
    public function forUser(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $tasks = array_values(array_filter([
            $this->pendingExams($user),
            $this->gradingQueue($user),
            $this->pendingReviews($user),
            $this->assignedTickets($user),
            $this->ownTickets($user),
        ]));

        return array_slice($tasks, 0, self::MAX_TASKS);
    }

    /**
     * Ujian yang sudah didistribusikan ke user dan belum selesai dikerjakan.
     */
    private function pendingExams(User $user): ?array
    {
        // `access-qa-assessment` adalah pintu masuk modul ujian; tanpa izin itu
        // tautan ke "ujian-saya" akan berakhir 403.
        if (! $user->can('access-qa-assessment')) {
            return null;
        }

        return $this->task(
            key: 'exams',
            label: 'Ujian menunggu',
            hint: 'Belum selesai dikerjakan',
            count: ExamAssignment::query()
                ->where('agent_id', $user->id)
                ->whereIn('status', ['assigned', 'in_progress'])
                ->count(),
            url: route('qa.exam.forms.my'),
            icon: 'exam',
        );
    }

    /**
     * Lembar jawaban yang sudah dikumpulkan agent dan menunggu dinilai QA.
     */
    private function gradingQueue(User $user): ?array
    {
        // Hanya pemegang izin menilai yang melihat antrean ini; Admin boleh
        // menerbitkan hasil tetapi tidak menilai (PRD: pemisahan wadah vs isi).
        if (! $user->can('qa.exam.assignment.grade')) {
            return null;
        }

        // Sama persis dengan QaExamAttemptController::gradingIndex() dan
        // ExamAssignmentPolicy::grade(): QA hanya menilai penugasan yang ia
        // terbitkan, sedangkan Admin boleh menilai semua. Sebelumnya hitungan
        // ini memakai `assigned_by` untuk SEMUA peran, sehingga Admin melihat
        // "0 menunggu dinilai" padahal daftar penilaiannya penuh.
        $query = ExamAssignment::query()->where('status', 'submitted');

        if (! $user->hasRole('admin')) {
            $query->where('assigned_by', $user->id);
        }

        return $this->task(
            key: 'grading',
            label: 'Menunggu dinilai',
            hint: 'Jawaban ujian yang sudah dikumpulkan',
            count: $query->count(),
            url: route('qa.exam.grading.index', ['status' => 'submitted']),
            icon: 'grade',
        );
    }

    /**
     * Assessment QA Sampling yang sudah dikirim dan menunggu diperiksa.
     *
     * AGENT: asisten | 2026-10-16 | tambah tugas pemeriksaan sampling | modul Dashboard per peran
     *
     * Sebelum ini supervisor hanya tahu ada pekerjaan menunggu setelah membuka
     * menu QA Sampling. Cakupannya memakai DashboardScope, jadi supervisor
     * tidak pernah menghitung pekerjaan tim lain.
     */
    private function pendingReviews(User $user): ?array
    {
        if (! $user->can('qa_sampling.review')) {
            return null;
        }

        $scope = DashboardScope::for($user);

        return $this->task(
            key: 'qa_reviews',
            label: 'Menunggu diperiksa',
            hint: 'Assessment QA yang sudah dikirim',
            count: $scope->constrain(QaSamplingAssessment::query())
                ->where('status', 'submitted')
                ->count(),
            url: route('qa.sampling.index'),
            icon: 'grade',
        );
    }

    /**
     * Tiket yang ditugaskan kepada user sebagai penanggung jawab (tim IT).
     */
    private function assignedTickets(User $user): ?array
    {
        if (! $user->can('manage-tickets')) {
            return null;
        }

        return $this->task(
            key: 'assigned_tickets',
            label: 'Tiket ditugaskan',
            hint: 'Menunggu ditangani',
            count: Ticket::query()
                ->where('assigned_to', $user->id)
                ->open()
                ->count(),
            url: route('it.tickets.index'),
            icon: 'ticket',
        );
    }

    /**
     * Tiket milik user sendiri yang belum tuntas — terbuka untuk semua peran.
     */
    private function ownTickets(User $user): ?array
    {
        return $this->task(
            key: 'own_tickets',
            label: 'Tiket saya',
            hint: 'Masih terbuka atau diproses',
            count: Ticket::query()
                ->where('user_id', $user->id)
                ->open()
                ->count(),
            url: route('tickets.index'),
            icon: 'ticket',
        );
    }

    /**
     * Bungkus satu tugas; kembalikan null bila tidak ada yang menunggu.
     *
     * @return array{key: string, label: string, hint: string, count: int, url: string, icon: string}|null
     */
    private function task(string $key, string $label, string $hint, int $count, string $url, string $icon): ?array
    {
        if ($count < 1) {
            return null;
        }

        return compact('key', 'label', 'hint', 'count', 'url', 'icon');
    }
}
