<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Item assessment QA Sampling (PRD Employee KPI/QA §11/§16).
 *
 * AGENT: asisten | 2026-10-03 | buat berkas baru | modul KPI, fase 2 QA sampling
 *
 * Satu baris = satu parameter yang dinilai pada satu assessment.
 *
 * Seluruh kolom `*_snapshot` sengaja disalin dari master SAAT assessment
 * dibuat (Weight Snapshot, PRD §11). Perhitungan skor hanya boleh membaca
 * kolom-kolom ini, tidak pernah master, supaya "data historis tidak berubah
 * hanya karena master berubah" (PRD §38).
 */
class QaSamplingItem extends Model
{
    use HasFactory;

    protected $table = 'qa_sampling_items';

    protected $fillable = [
        'qa_sampling_assessment_id',
        'scorecard_parameter_id',
        'category_name_snapshot',
        'parameter_name_snapshot',
        'weight_snapshot',
        'parameter_type_snapshot',
        'ztp_effect_snapshot',
        'answer_polarity_snapshot',
        'is_required_snapshot',
        'answer',
        'is_violation',
        'penalty',
        'comment',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'weight_snapshot' => 'decimal:2',
            'penalty' => 'decimal:2',
            'is_required_snapshot' => 'boolean',
            'is_violation' => 'boolean',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(QaSamplingAssessment::class, 'qa_sampling_assessment_id');
    }

    public function parameter(): BelongsTo
    {
        return $this->belongsTo(ScorecardParameter::class, 'scorecard_parameter_id');
    }

    /**
     * @return list<string>
     */
    public static function answers(): array
    {
        return ['yes', 'no', 'na'];
    }

    /** Kelebihan: pre-render untuk radio YES/NO/NA di view. */
    public function hasAnswer(): bool
    {
        return $this->answer !== null;
    }

    public function isZeroToleranceScoreZero(): bool
    {
        return $this->parameter_type_snapshot === 'zero_tolerance'
            && $this->ztp_effect_snapshot === 'zero_score';
    }
}
