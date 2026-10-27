<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Parameter Scorecard (PRD Employee KPI/QA §8/§13).
 *
 * AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master
 *
 * Master aturan penilaian. Perhitungan skor akhir dikerjakan di Fase 2, tetapi
 * SEMUA bahan hitungannya sudah dinyatakan eksplisit di sini: `weight`,
 * `parameter_type`, `ztp_effect`, dan `answer_polarity`.
 *
 * Ringkasan makna jawaban:
 * - `normal`   : NO = penalty sebesar `weight`.
 * - `inverted` : YES = penalty sebesar `weight` (mis. ZTP "terjadi
 *                pelanggaran?" - seperti terlihat di form Excel: baris ZTP
 *                ber-jawaban "NO" justru berarti TIDAK ada pelanggaran).
 */
class ScorecardParameter extends Model
{
    use HasFactory;

    protected $fillable = [
        'scorecard_category_id',
        'name',
        'description',
        'weight',
        'evaluation_type',
        'parameter_type',
        'ztp_effect',
        'answer_polarity',
        'guideline',
        'penalty_rule',
        'is_required',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'is_required' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ScorecardCategory::class, 'scorecard_category_id');
    }

    /**
     * @return list<string>
     */
    public static function evaluationTypes(): array
    {
        return ['yes_no', 'yes_no_na'];
    }

    /**
     * @return list<string>
     */
    public static function parameterTypes(): array
    {
        return ['normal', 'critical', 'zero_tolerance'];
    }

    /**
     * @return list<string>
     */
    public static function ztpEffects(): array
    {
        return ['weight_only', 'zero_score'];
    }

    public function isZeroTolerance(): bool
    {
        return $this->parameter_type === 'zero_tolerance';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
