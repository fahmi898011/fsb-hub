<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Master Scorecard (PRD Employee KPI/QA §5).
 *
 * AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master
 *
 * Scorecard adalah pasangan (KPI, Channel). Bobot parameter TIDAK ada di sini
 * melainkan di ScorecardVersion, supaya perubahan bobot menghasilkan versi
 * baru dan assessment lama tetap utuh (PRD §6).
 */
class Scorecard extends Model
{
    use HasFactory;

    protected $fillable = [
        'kpi_id',
        'channel_id',
        'name',
        'slug',
        'description',
        'status',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $scorecard) {
            if (empty($scorecard->slug)) {
                $scorecard->slug = Str::slug($scorecard->name);
            }
        });
    }

    public function kpi(): BelongsTo
    {
        return $this->belongsTo(Kpi::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ScorecardVersion::class)->orderByDesc('version');
    }

    /**
     * Versi yang saat ini berlaku: versi `active` dengan nomor tertinggi.
     * Dipakai form QA untuk memuat parameter yang benar.
     */
    public function activeVersion(): ?ScorecardVersion
    {
        return $this->versions()
            ->where('status', 'active')
            ->orderByDesc('version')
            ->first();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
