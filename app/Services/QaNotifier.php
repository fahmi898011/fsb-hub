<?php

namespace App\Services;

use App\Models\ExamAssignment;
use App\Models\QaAssessment;

/**
 * Layanan notifikasi modul QA Assessment (PRD QA Assessment §12).
 *
 * AGENT: asisten | 2026-10-08 | perbaiki | selaraskan dengan skema nyata & NotificationService
 *
 * Memakai NotificationService (pola QaSamplingNotifier). `type` selalu
 * `qa_assessment` atau `qa_exam` agar ikon di daftar notifikasi konsisten
 * (lihat AppNotification::getIconAttribute()).
 */
class QaNotifier
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * Assessment diterbitkan -> beri tahu agent yang dinilai.
     */
    public function assessmentPublished(QaAssessment $assessment): int
    {
        $this->notifications->notify(
            $assessment->agent_id,
            "Penilaian QA: {$assessment->period_name_snapshot}",
            "Anda dinilai oleh {$assessment->qa_name_snapshot} pada periode {$assessment->period_name_snapshot}.",
            'qa_assessment',
            route('qa.assessments.show', $assessment),
        );

        return 1;
    }

    /**
     * Ujian ditugaskan ke agent -> beri tahu agent.
     */
    public function examOpened(ExamAssignment $assignment): void
    {
        $duration = $assignment->form->duration_minutes;

        $this->notifications->notify(
            $assignment->agent_id,
            "Ujian Baru: {$assignment->form->title}",
            'Ujian telah dibuka.' . ($duration ? " Batas waktu {$duration} menit." : ''),
            'qa_exam',
            route('qa.exam.forms.take', $assignment),
        );
    }

    /**
     * Hasil ujian dipublikasikan -> beri tahu agent.
     */
    public function examResultPublished(ExamAssignment $assignment): void
    {
        $this->notifications->notify(
            $assignment->agent_id,
            "Hasil Ujian: {$assignment->form->title}",
            'Skor Anda ' . number_format((float) $assignment->percentage, 2) . '%'
                . ($assignment->result ? " ({$assignment->result})" : '') . '.',
            'qa_exam',
            route('qa.exam.forms.result', $assignment),
        );
    }
}
