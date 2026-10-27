<?php

namespace App\Services;

use App\Models\ExamAnswer;
use App\Models\ExamAssignment;
use App\Models\ExamForm;
use App\Models\ExamFormQuestion;

/**
 * Layanan penghitungan skor ujian QA Assessment (PRD QA Assessment §5).
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian B
 *
 * Menangani: auto-grade multiple_choice, hitung total/percentage, tentukan pass/fail,
 * pilih skor saat retake (highest/latest/average).
 */
class QaAssessmentScoringService
{
    /**
     * Auto-grade jawaban multiple choice & hitung skor assignment.
     *
     * @param  bool  $finalize  `true` = tandai assignment selesai dinilai
     *                          (`graded`). Dipakai saat agent mengumpulkan
     *                          ujian digital dengan `false`: pilihan ganda
     *                          tetap dinilai otomatis, tetapi status harus
     *                          tetap `submitted` karena essay masih menunggu
     *                          QA. Policy `grade` hanya meloloskan status
     *                          `submitted`; kalau di sini dipaksa `graded`,
     *                          halaman penilaian menolak QA (403) dan essay
     *                          tidak akan pernah bisa dinilai.
     */
    public function gradeAssignment(ExamAssignment $assignment, bool $finalize = true): ExamAssignment
    {
        $assignment->load('form.questions', 'answers');

        $totalScore = 0;
        // Semua soal dihitung ke max_score, termasuk yg tidak dijawab (jawaban kosong = 0 poin).
        $maxScore = $assignment->form->questions->sum('point');

        foreach ($assignment->form->questions as $question) {
            $answer = $assignment->answers->firstWhere('question_id', $question->id);

            if (! $answer) {
                continue; // soal tidak dijawab = 0 poin
            }

            if ($question->question_type_snapshot === ExamFormQuestion::TYPE_MULTIPLE_CHOICE) {
                // Auto-grade multiple choice
                $score = $this->gradeMultipleChoice($question, $answer);
                $answer->score = $score;

                // Penilai baru dicatat saat finalisasi: pada `finish()` yang
                // sedang login adalah agent, dan agent tidak boleh tercatat
                // sebagai penilai jawabannya sendiri.
                if ($finalize) {
                    $answer->graded_by = auth()->id();
                    $answer->graded_at = now();
                }

                $answer->save();

                $totalScore += $score;
            } else {
                // Essay: butuh grading manual QA. Skor yg sudah diisi QA tetap dihitung.
                $totalScore += (float) ($answer->score ?? 0);
            }
        }

        // Update assignment totals
        $assignment->max_score = $maxScore;
        $assignment->total_score = $totalScore;
        $assignment->percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0;

        // Tentukan pass/fail jika passing_score diset
        if ($assignment->form->passing_score !== null) {
            $assignment->result = $assignment->percentage >= $assignment->form->passing_score ? 'pass' : 'fail';
        }

        // Handle retake: pilih skor yg diambil (highest/latest/average)
        if ($assignment->form->allow_retake && $assignment->attempt_ke > 1) {
            $assignment->score_picked = $assignment->form->grading_method;
            $assignment = $this->applyRetakeScoring($assignment);
        }

        // `graded_by`/`graded_at` hanya diisi saat benar-benar final. Kalau
        // diisi pada saat agent menekan "Kumpulkan Jawaban", kolom itu akan
        // mencatat AGENT sebagai penilai — jejak audit yang keliru.
        if ($finalize) {
            $assignment->status = 'graded';
            $assignment->graded_by = auth()->id();
            $assignment->graded_at = now();
        }

        $assignment->save();

        return $assignment->fresh();
    }

    /**
     * Grade soal multiple choice: bandingkan jawaban agent dengan kunci.
     */
    protected function gradeMultipleChoice(ExamFormQuestion $question, ExamAnswer $answer): float
    {
        $selected = $answer->answer['selected'] ?? (array) ($answer->answer['value'] ?? []);
        $correctOptions = collect($question->options_snapshot ?? [])
            ->where('is_correct', true)
            ->pluck('label')
            ->all();

        // Benar jika semua pilihan benar dipilih DAN tidak ada pilihan salah
        $isCorrect = count(array_diff($correctOptions, $selected)) === 0
            && count(array_diff($selected, $correctOptions)) === 0;

        return $isCorrect ? (float) $question->point : 0.0;
    }

    /**
     * Terapkan logika retake scoring (highest/latest/average).
     */
    protected function applyRetakeScoring(ExamAssignment $current): ExamAssignment
    {
        $previousAttempts = ExamAssignment::where('form_id', $current->form_id)
            ->where('agent_id', $current->agent_id)
            ->where('attempt_ke', '<', $current->attempt_ke)
            ->whereIn('status', ['graded', 'published'])
            ->get();

        if ($previousAttempts->isEmpty()) {
            return $current;
        }

        $allPercentages = $previousAttempts->pluck('percentage')->push($current->percentage)->filter()->all();

        $pickedPercentage = match ($current->form->grading_method) {
            'highest' => max($allPercentages),
            'latest' => $current->percentage,
            'average' => array_sum($allPercentages) / count($allPercentages),
            default => $current->percentage,
        };

        // Recalculate total_score based on picked percentage
        $current->percentage = round($pickedPercentage, 2);
        $current->total_score = round(($pickedPercentage / 100) * $current->max_score, 2);

        if ($current->form->passing_score !== null) {
            $current->result = $pickedPercentage >= $current->form->passing_score ? 'pass' : 'fail';
        }

        return $current;
    }

    /**
     * Finalisasi grading setelah QA isi nilai essay.
     */
    public function finalizeGrading(ExamAssignment $assignment): ExamAssignment
    {
        $assignment->load('form.questions', 'answers.question');

        // max_score dari seluruh soal form, bukan hanya yg dijawab.
        $maxScore = $assignment->form->questions->sum('point');
        $totalScore = $assignment->answers->sum('score');

        $assignment->total_score = $totalScore;
        $assignment->max_score = $maxScore;
        $assignment->percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0;

        if ($assignment->form->passing_score !== null) {
            $assignment->result = $assignment->percentage >= $assignment->form->passing_score ? 'pass' : 'fail';
        }

        // Handle retake scoring
        if ($assignment->form->allow_retake && $assignment->attempt_ke > 1) {
            $assignment->score_picked = $assignment->form->grading_method;
            $this->applyRetakeScoring($assignment);
        }

        $assignment->status = 'graded';
        $assignment->graded_by = auth()->id();
        $assignment->graded_at = now();
        $assignment->save();

        return $assignment->fresh();
    }

    /**
     * Hitung ulang total/max/persentase TANPA mengubah status.
     *
     * AGENT: asisten | 2026-10-16 | tambah hitung ulang tanpa ubah status | modul Quiz -> Ujian
     *
     * Dipakai mode kertas: setelah QA menandai "dikumpulkan", assignment harus
     * tetap `submitted` (supaya masuk antrean penilaian), tetapi angka yang
     * tampil di halaman penilaian sudah harus benar. `gradeAssignment()` tidak
     * bisa dipakai di sini karena ia SELALU memindahkan status ke `graded` —
     * akibatnya QA tidak lagi punya hak mengisi skor essay.
     */
    public function recalculateTotals(ExamAssignment $assignment): ExamAssignment
    {
        $assignment->load('form.questions', 'answers');

        $maxScore = (float) $assignment->form->questions->sum('point');
        $totalScore = (float) $assignment->answers->sum('score');

        $assignment->max_score = $maxScore;
        $assignment->total_score = $totalScore;
        $assignment->percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0;

        if ($assignment->form->passing_score !== null) {
            $assignment->result = $assignment->percentage >= $assignment->form->passing_score ? 'pass' : 'fail';
        }

        $assignment->save();

        return $assignment->fresh();
    }

    /**
     * Publish hasil ke agent.
     */
    public function publishResult(ExamAssignment $assignment): ExamAssignment
    {
        $assignment->status = 'published';
        $assignment->published_by = auth()->id();
        $assignment->published_at = now();
        $assignment->save();

        return $assignment->fresh();
    }

    /**
     * Cek apakah assignment sudah bisa di-grade (semua essay sudah dinilai).
     */
    public function isFullyGraded(ExamAssignment $assignment): bool
    {
        $assignment->load('answers.question');

        foreach ($assignment->answers as $answer) {
            if ($answer->question->question_type_snapshot === 'essay' && $answer->score === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * Ringkasan skor untuk laporan/dashboard.
     */
    public function getScoreSummary(ExamForm $form): array
    {
        $assignments = ExamAssignment::where('form_id', $form->id)
            ->where('status', 'published')
            ->get();

        if ($assignments->isEmpty()) {
            return [
                'total_assignees' => 0,
                'completed' => 0,
                'passed' => 0,
                'failed' => 0,
                'avg_percentage' => 0,
                'highest_percentage' => 0,
                'lowest_percentage' => 0,
            ];
        }

        $percentages = $assignments->pluck('percentage')->filter()->all();

        return [
            'total_assignees' => $assignments->count(),
            'completed' => $assignments->where('status', 'published')->count(),
            'passed' => $assignments->where('result', 'pass')->count(),
            'failed' => $assignments->where('result', 'fail')->count(),
            'avg_percentage' => count($percentages) ? round(array_sum($percentages) / count($percentages), 2) : 0,
            'highest_percentage' => count($percentages) ? round(max($percentages), 2) : 0,
            'lowest_percentage' => count($percentages) ? round(min($percentages), 2) : 0,
        ];
    }
}
