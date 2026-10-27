<?php

namespace App\Services;

use App\Models\QuestionBank;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;

/**
 * Penilaian quiz (PRD Employee KPI/QA §25/§26/§27).
 *
 * AGENT: asisten | 2026-10-05 | buat berkas baru | modul Quiz, fase 4 quiz
 *
 * Prinsip yang sama dengan QaSamplingScoringService: SKOR TIDAK PERNAH diterima
 * dari frontend. Halaman hanya mengirim pilihan jawaban; benar/salah dan skor
 * dihitung di sini (PRD §10/§45).
 *
 * Formula (PRD §25):
 *
 *   Score = Earned Point / Total Point x 100
 *
 * Poin tiap soal diambil dari `point_snapshot` (beku saat versi dibuat), bukan
 * dari poin soal di bank. Dengan begitu mengubah poin di bank tidak mengubah
 * hasil quiz yang sudah berjalan (PRD §11/§23).
 */
class QuizScoringService
{
    /**
     * Periksa apakah jawaban seorang peserta BENAR untuk sebuah soal.
     *
     * Aturan kecocokan (PRD §21 "Correct Answer"):
     *  - Jawaban benar = himpunan opsi yang bertanda `is_correct`.
     *  - Peserta harus memilih TEPAT himpunan itu. Untuk `multiple`, memilih
     *    sebagian saja (kurang atau lebih) dianggap SALAH. Ini sengaja ketat:
     *    soal pilihan ganda banyak-jawaban umumnya menguji kelengkapan, dan
     *    memberi poin sebagian (partial credit) bukan aturan yang diminta PRD.
     *
     * @param  list<int>  $selectedOptionIds  opsi yang dipilih peserta
     * @param  list<int>  $correctOptionIds   kunci jawaban
     */
    public function isCorrect(array $selectedOptionIds, array $correctOptionIds): bool
    {
        $selected = array_values(array_unique(array_map('intval', $selectedOptionIds)));
        $correct = array_values(array_unique(array_map('intval', $correctOptionIds)));

        sort($selected);
        sort($correct);

        return $selected === $correct;
    }

    /**
     * Hitung ulang seluruh hasil attempt dari jawaban yang tersimpan.
     *
     * Dipanggil setelah peserta submit. Menghasilkan angka final yang disimpan
     * ke attempt (PRD §27), bukan dihitung ulang setiap tampil.
     */
    public function finalize(QuizAttempt $attempt): QuizAttempt
    {
        $answers = $attempt->answers()->get();

        $totalPoint = 0;
        $earnedPoint = 0;
        $correct = 0;
        $wrong = 0;

        foreach ($answers as $answer) {
            $totalPoint += (int) $answer->point_snapshot;

            if ($answer->is_correct) {
                $earnedPoint += (int) $answer->point_snapshot;
                $correct++;
            } else {
                $wrong++;
            }
        }

        $score = $totalPoint > 0
            ? round(($earnedPoint / $totalPoint) * 100, 2)
            : 0.0;

        $passingScore = (float) $attempt->passing_score_snapshot;

        $attempt->update([
            'total_questions' => $answers->count(),
            'correct_count' => $correct,
            'wrong_count' => $wrong,
            'total_point' => $totalPoint,
            'earned_point' => $earnedPoint,
            'score' => $score,
            'result' => $score >= $passingScore ? 'pass' : 'fail',
            'completed_at' => now(),
        ]);

        return $attempt->fresh();
    }

    /**
     * Bangun baris jawaban (belum dinilai) dari soal-soal versi, lalu tandai
     * benar/salah untuk yang sudah dijawab.
     *
     * Dipisah dari `finalize()` supaya pengisian jawaban bertahap (bila nanti
     * diaktifkan) hanya perlu memanggil ulang penilaian tanpa membangun ulang
     * daftar soal.
     *
     * @param  array<int, list<int>>  $selectedByVersionQuestionId  peta id soal versi -> opsi dipilih
     */
    public function recordAnswers(QuizAttempt $attempt, array $selectedByVersionQuestionId): void
    {
        $attempt->answers()->delete();

        $rows = [];
        $now = now();

        foreach ($attempt->version?->questions()->with('question.options')->get() ?? collect() as $versionQuestion) {
            $selected = $selectedByVersionQuestionId[$versionQuestion->id] ?? [];

            // Opsi/kunci ikut disnapshot ke versi. Perubahan pada bank soal
            // setelah quiz diterbitkan tidak boleh mengubah nilai attempt.
            $correctOptionIds = collect($versionQuestion->options_snapshot ?? [])
                ->where('is_correct', true)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $rows[] = [
                'quiz_attempt_id' => $attempt->getKey(),
                'quiz_version_question_id' => $versionQuestion->getKey(),
                'question_snapshot' => $versionQuestion->question_snapshot,
                'selected_option_ids' => json_encode(array_values(array_map('intval', $selected))),
                'is_correct' => $this->isCorrect($selected, $correctOptionIds),
                'point_snapshot' => $versionQuestion->point_snapshot,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            QuizAnswer::insert($rows);
        }
    }
}
