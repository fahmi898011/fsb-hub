<?php

namespace App\Services;

use App\Models\QaSamplingAssessment;
use App\Models\ScorecardVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Pembuatan assessment QA Sampling dari master scorecard.
 *
 * AGENT: asisten | 2026-10-03 | buat berkas baru | modul KPI, fase 2 QA sampling
 *
 * Berkas ini menjalankan dua prinsip sekaligus saat assessment dibuat:
 *
 * 1. PRD §11/§38 - Weight Snapshot. Nama, bobot, tipe parameter, efek ZTP, dan
 *    arah jawaban DISALIN dari master ke setiap item. Mulai titik ini,
 *    assessment tidak lagi bergantung pada master: master boleh berubah, angka
 *    historis tetap.
 *
 * 2. PRD §14 - memilih versi AKTIF. Versi diambil eksplisit lewat parameter,
 *    bukan ditebak dari tanggal, supaya QA memakai bobot yang benar-benar
 *    berlaku saat ia menilai.
 *
 * Penyalinan dilakukan dalam SATU transaksi bersama pembuatan header, agar
 * tidak pernah ada assessment "setengah jadi" tanpa item.
 */
class QaSamplingService
{
    /**
     * Buat assessment baru beserta seluruh item snapshot-nya.
     *
     * @param  array{employee_id:int, scorecard_version_id:int, sample_link?:?string, sample_id?:?string, assessment_date:string, period?:?string, notes?:?string, qa_id?:?int}  $data
     */
    public function createAssessment(array $data, User $actor): QaSamplingAssessment
    {
        $version = ScorecardVersion::query()
            ->with(['scorecard.kpi', 'scorecard.channel'])
            ->findOrFail($data['scorecard_version_id']);

        $employee = User::find($data['employee_id']);

        return DB::transaction(function () use ($data, $version, $actor, $employee) {
            /** @var QaSamplingAssessment $assessment */
            $assessment = QaSamplingAssessment::create([
                'employee_id' => $data['employee_id'],
                'qa_id' => $data['qa_id'] ?? $actor->id,
                'department_id' => $employee?->department_id,
                'scorecard_id' => $version->scorecard_id,
                'scorecard_version_id' => $version->getKey(),
                'channel_id' => $version->scorecard?->channel_id,

                // Snapshot identitas.
                'employee_name_snapshot' => $employee?->name,
                'scorecard_name_snapshot' => $version->scorecard?->name ?? 'Scorecard',
                'version_number_snapshot' => $version->version,
                'channel_name_snapshot' => $version->scorecard?->channel?->name,

                'sample_link' => $data['sample_link'] ?? null,
                'sample_id' => $data['sample_id'] ?? null,
                'assessment_date' => $data['assessment_date'],
                'period' => $data['period'] ?? null,
                'status' => 'draft',
                'initial_score' => QaSamplingScoringService::INITIAL_SCORE,
                'penalty_total' => 0,
                'final_score' => QaSamplingScoringService::INITIAL_SCORE,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->snapshotItems($assessment, $version);

            return $assessment;
        });
    }

    /**
     * Salin parameter versi (yang aktif) menjadi item assessment.
     *
     * Hanya parameter `active` yang disalin: parameter yang sudah dinonaktifkan
     * Admin tidak lagi menjadi bagian penilaian versi ini.
     */
    public function snapshotItems(QaSamplingAssessment $assessment, ScorecardVersion $version): void
    {
        $rows = [];

        foreach ($version->categories as $category) {
            foreach ($category->parameters()->where('status', 'active')->orderBy('sort_order')->orderBy('id')->get() as $parameter) {
                $rows[] = [
                    'qa_sampling_assessment_id' => $assessment->getKey(),
                    'scorecard_parameter_id' => $parameter->getKey(),
                    'category_name_snapshot' => $category->name,
                    'parameter_name_snapshot' => $parameter->name,
                    'weight_snapshot' => $parameter->weight,
                    'parameter_type_snapshot' => $parameter->parameter_type,
                    'ztp_effect_snapshot' => $parameter->ztp_effect,
                    'answer_polarity_snapshot' => $parameter->answer_polarity,
                    'is_required_snapshot' => $parameter->is_required,
                    'answer' => null,
                    'is_violation' => false,
                    'penalty' => 0,
                    'comment' => null,
                    'sort_order' => $parameter->sort_order,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if ($rows !== []) {
            $assessment->items()->insert($rows);
        }
    }

    /**
     * Parameter WAJIB yang belum dinilai (PRD §46: tidak boleh submit bila ada
     * parameter required yang kosong).
     *
     * @return list<string> nama parameter yang belum dinilai
     */
    public function missingRequiredAnswers(QaSamplingAssessment $assessment): array
    {
        return $assessment->items()
            ->whereNull('answer')
            ->where('is_required_snapshot', true)
            ->pluck('parameter_name_snapshot')
            ->all();
    }
}
