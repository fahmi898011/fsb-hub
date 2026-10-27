<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * KPI (PRD Employee KPI/QA §4).
 *
 * AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master
 *
 * KPI menjawab "apa yang diukur" untuk sebuah divisi (mis. Quality Score).
 * Cara mengukurnya ada di Scorecard.
 *
 * Catatan penamaan: kelas bernama `Kpi` (bukan `KPI`) karena nama kelas harus
 * konsisten dengan tabel `kpis` hasil konvensi Eloquent di seluruh aplikasi.
 */
class Kpi extends Model
{
    use HasFactory;

    protected $table = 'kpis';

    protected $fillable = [
        'department_id',
        'name',
        'slug',
        'code',
        'description',
        'status',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $kpi) {
            if (empty($kpi->slug)) {
                $kpi->slug = Str::slug($kpi->name);
            }
        });
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function scorecards(): HasMany
    {
        return $this->hasMany(Scorecard::class);
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
