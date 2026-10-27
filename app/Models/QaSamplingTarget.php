<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Target sampling mingguan (PRD Employee KPI/QA Sampling §17/§18).
 *
 * AGENT: asisten | 2026-10-04 | buat berkas baru | modul KPI, fase 3 QA reporting
 *
 * Aturan ini adalah angka pembanding bagi laporan sampling ("Target 100,
 * Completed 87, Completion 87%", PRD §17). Karena itu ia disimpan sebagai
 * data — bukan konstanta di dalam kode — supaya Admin dapat mengubah kebijakan
 * tanpa deploy, dan supaya laporan periode lampau dapat dihitung dengan aturan
 * yang berlaku pada periode itu.
 *
 * `department_id`/`scorecard_id` yang NULL berarti "aturan ini berlaku umum
 * untuk semua", bukan "tanpa aturan". Urutan pencarian aturan yang paling
 * spesifik (=cocok divisi & scorecard) sampai paling umum ditangani
 * `QaSamplingReportService::targetFor()`.
 */
class QaSamplingTarget extends Model
{
    use HasFactory;

    protected $table = 'qa_sampling_targets';

    protected $fillable = [
        'department_id',
        'scorecard_id',
        'frequency',
        'target_type',
        'target_value',
        'effective_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'target_value' => 'integer',
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Relasi                                                              */
    /* ------------------------------------------------------------------ */

    public function division(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function scorecard(): BelongsTo
    {
        return $this->belongsTo(Scorecard::class);
    }

    /* ------------------------------------------------------------------ */
    /* Bantu                                                               */
    /* ------------------------------------------------------------------ */

    /**
     * @return list<string>
     */
    public static function targetTypes(): array
    {
        return ['per_employee', 'per_qa', 'per_team', 'per_division', 'total'];
    }

    /**
     * @return list<string>
     */
    public static function frequencies(): array
    {
        return ['weekly', 'monthly'];
    }

    /**
     * Label jenis target dalam bahasa Indonesia untuk tampilan.
     */
    public function getTargetTypeLabelAttribute(): string
    {
        return match ($this->target_type) {
            'per_employee' => 'Per Karyawan',
            'per_qa' => 'Per QA',
            'per_team' => 'Per Tim',
            'per_division' => 'Per Divisi',
            'total' => 'Total Mingguan',
            default => ucfirst((string) $this->target_type),
        };
    }

    public function getFrequencyLabelAttribute(): string
    {
        return match ($this->frequency) {
            'weekly' => 'Mingguan',
            'monthly' => 'Bulanan',
            default => ucfirst((string) $this->frequency),
        };
    }

    /* ------------------------------------------------------------------ */
    /* Scope                                                               */
    /* ------------------------------------------------------------------ */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Aturan yang sudah berlaku pada tanggal tertentu (PRD §38: aturan historis
     * tidak boleh berubah karena Admin menyunting konfigurasi baru).
     */
    public function scopeEffectiveOn(Builder $query, string $date): Builder
    {
        return $query->whereDate('effective_date', '<=', $date);
    }
}
