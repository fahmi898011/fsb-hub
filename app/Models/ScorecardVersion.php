<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Versi Scorecard (PRD Employee KPI/QA §6).
 *
 * AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master
 *
 * Kategori dan parameter menggantung pada VERSI (bukan pada scorecard), sebab
 * yang berubah dari waktu ke waktu adalah susunan bobotnya. Versi yang sudah
 * dipakai assessment tidak boleh diubah.
 */
class ScorecardVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'scorecard_id',
        'version',
        'status',
        'effective_from',
        'effective_to',
        'notes',
        'published_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'published_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function scorecard(): BelongsTo
    {
        return $this->belongsTo(Scorecard::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(ScorecardCategory::class)->orderBy('sort_order');
    }

    public function parameters(): HasManyThrough
    {
        return $this->hasManyThrough(
            ScorecardParameter::class,
            ScorecardCategory::class,
            'scorecard_version_id',
            'scorecard_category_id',
        )->orderBy('scorecard_parameters.sort_order');
    }

    /**
     * Total bobot parameter yang benar-benar dihitung untuk skor (bukan ZTP
     * pelanggaran, yang justru bermakna terbalik). Dipakai sebelum publish
     * untuk memastikan total 100% (PRD §37).
     */
    public function totalWeight(): float
    {
        return (float) $this->parameters()
            ->where('scorecard_parameters.status', 'active')
            ->where(function (Builder $q) {
                // Parameter normal selalu dihitung.
                $q->where('scorecard_parameters.parameter_type', '!=', 'zero_tolerance')
                    // ZTP yang memaksa skor 0 tidak menyumbang bobot ke total.
                    ->orWhere(function (Builder $q) {
                        $q->where('scorecard_parameters.parameter_type', 'zero_tolerance')
                            ->where('scorecard_parameters.ztp_effect', 'weight_only');
                    });
            })
            ->sum('scorecard_parameters.weight');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
