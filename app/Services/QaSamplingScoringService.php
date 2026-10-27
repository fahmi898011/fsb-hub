<?php

namespace App\Services;

use App\Models\QaSamplingAssessment;
use App\Models\QaSamplingItem;

/**
 * Mesin perhitungan Quality Score QA Sampling (PRD Employee KPI/QA §9-§13/§44).
 *
 * AGENT: asisten | 2026-10-03 | buat berkas baru | modul KPI, fase 2 QA sampling
 *
 * Dua aturan PRD yang membentuk berkas ini:
 *
 * 1. §10 - "Jangan mutasi score secara langsung". Skor TIDAK dikurangi satu per
 *    satu saat QA menekan NO. Setiap jawaban disimpan, lalu skor akhir DIHITUNG
 *    ULANG dari seluruh item. Karena itulah perhitungannya dikumpulkan di satu
 *    tempat (kelas ini), bukan tersebar di controller maupun JavaScript.
 *
 * 2. §11 - "Weight Snapshot". Perhitungan HANYA membaca kolom `*_snapshot`
 *    milik item, TIDAK pernah menyentuh master ScorecardParameter. Dengan
 *    begitu angka September tetap 6% walau master Oktober menjadi 8%.
 *
 * Formula (§9):
 *     Final Score = 100 - SUM(penalty item yang kena aturan)
 * dibatasi minimum 0 (§46), dan dipaksa 0 bila ZTP `zero_score` tersentuh (§13).
 *
 * Perhitungan ini murni (tanpa DB) supaya mudah diuji; lihat
 * `evaluateAnswer()` dan `summarize()`.
 */
class QaSamplingScoringService
{
    /** Skor awal setiap assessment (PRD §9). */
    public const INITIAL_SCORE = 100.0;

    /**
     * Hitung hasil SATU jawaban terhadap aturan parameter.
     *
     * Arah "kena aturan" ditentukan `answer_polarity`, bukan teks jawaban
     * (PRD §8 + catatan migration): parameter `normal` kena saat jawabannya
     * NO, parameter `inverted` (mis. ZTP "apakah terjadi pelanggaran?") kena
     * saat jawabannya YES.
     *
     * @return array{is_violation: bool, penalty: float, zero_score: bool}
     */
    public function evaluateAnswer(
        ?string $answer,
        string $answerPolarity,
        string $parameterType,
        string $ztpEffect,
        float $weight,
    ): array {
        $neutral = ['is_violation' => false, 'penalty' => 0.0, 'zero_score' => false];

        // Belum dinilai.
        if ($answer === null || $answer === '') {
            return $neutral;
        }

        // NA tidak dianggap YES dan tidak dianggap pelanggaran (PRD §12).
        // Default yang dipakai saat ini: NA = 0 penalty.
        if ($answer === 'na') {
            return $neutral;
        }

        $isViolation = $answerPolarity === 'inverted'
            ? $answer === 'yes'
            : $answer === 'no';

        if (! $isViolation) {
            return $neutral;
        }

        // ZTP yang memaksa skor menjadi 0 (PRD §13): tidak berupa potongan
        // bobot, melainkan penghukuman langsung ke 0%.
        if ($parameterType === 'zero_tolerance' && $ztpEffect === 'zero_score') {
            return ['is_violation' => true, 'penalty' => 0.0, 'zero_score' => true];
        }

        // Normal, critical, dan ZTP `weight_only` semuanya berbentuk potongan
        // bobot. (`critical` belum punya rule final di PRD §13 - placeholder
        // permintaannya sama dengan normal, diputuskan saat business
        // mengonfirmasi.)
        return ['is_violation' => true, 'penalty' => round($weight, 2), 'zero_score' => false];
    }

    /**
     * Rangkum seluruh item menjadi skor akhir (PRD §9/§13/§46).
     *
     * @param  iterable<QaSamplingItem>  $items
     * @return array{penalty_total: float, final_score: float, zero_tolerance_triggered: bool, scored_count: int}
     */
    public function summarize(iterable $items): array
    {
        $penaltyTotal = 0.0;
        $zeroScore = false;
        $scored = 0;

        foreach ($items as $item) {
            $penaltyTotal += (float) ($item->penalty ?? 0);

            if ($item->is_violation) {
                $scored++;
            }

            if ($item->is_violation
                && $item->parameter_type_snapshot === 'zero_tolerance'
                && $item->ztp_effect_snapshot === 'zero_score') {
                $zeroScore = true;
            }
        }

        $penaltyTotal = round($penaltyTotal, 2);

        // Skor tidak boleh negatif (PRD §46); ZTP memaksa 0 (PRD §13).
        $final = $zeroScore ? 0.0 : max(0.0, round(self::INITIAL_SCORE - $penaltyTotal, 2));

        return [
            'penalty_total' => $penaltyTotal,
            'final_score' => $final,
            'zero_tolerance_triggered' => $zeroScore,
            'scored_count' => $scored,
        ];
    }

    /**
     * Hitung ulang SATU item dari jawaban yang tersimpan, lalu simpan hasilnya.
     * Dipakai setiap kali QA mengubah jawaban (PRD §10).
     */
    public function recalculateItem(QaSamplingItem $item, ?string $answer): QaSamplingItem
    {
        $result = $this->evaluateAnswer(
            $answer,
            $item->answer_polarity_snapshot,
            $item->parameter_type_snapshot,
            $item->ztp_effect_snapshot,
            (float) $item->weight_snapshot,
        );

        $item->forceFill([
            'answer' => $answer,
            'is_violation' => $result['is_violation'],
            'penalty' => $result['penalty'],
        ])->save();

        return $item;
    }

    /**
     * Hitung ulang SELURUH item pada sebuah assessment lalu simpan totalnya.
     *
     * Inilah pengganti "mutasi skor" yang dilarang PRD §10: yang disimpan
     * hanyalah hasil hitung ulang dari jawaban-jawaban yang ada.
     */
    public function recalculateAssessment(QaSamplingAssessment $assessment): QaSamplingAssessment
    {
        $summary = $this->summarize($assessment->items()->get());

        $assessment->forceFill([
            'initial_score' => self::INITIAL_SCORE,
            'penalty_total' => $summary['penalty_total'],
            'final_score' => $summary['final_score'],
            'zero_tolerance_triggered' => $summary['zero_tolerance_triggered'],
        ])->save();

        return $assessment;
    }
}
