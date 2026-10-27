<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kategori Scorecard (PRD Employee KPI/QA §7).
 *
 * AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master
 *
 * Kategori hanya mengelompokkan parameter agar form QA rapi. Melekat pada
 * versi scorecard sebab susunan kategori ikut berubah bersama bobot.
 */
class ScorecardCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'scorecard_version_id',
        'name',
        'description',
        'status',
        'sort_order',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(ScorecardVersion::class, 'scorecard_version_id');
    }

    public function parameters(): HasMany
    {
        return $this->hasMany(ScorecardParameter::class)->orderBy('sort_order');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
