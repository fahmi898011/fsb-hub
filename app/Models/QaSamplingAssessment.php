<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Assessment QA Sampling (PRD Employee KPI/QA §14/§15/§16).
 *
 * AGENT: asisten | 2026-10-03 | buat berkas baru | modul KPI, fase 2 QA sampling
 *
 * Satu baris = satu sample (link) yang dinilai QA untuk seorang karyawan.
 *
 * Nama kelas `QaSamplingAssessment` (bukan `QaAssessment`) dipilih agar tidak
 * bertabrakan dengan modul penilaian kualitatif "PRD QA Assessment" yang juga
 * ingin memakai nama itu. Lihat docblock migration untuk alasan lengkapnya.
 *
 * Status (PRD §16):
 *   draft     - masih boleh disunting QA.
 *   submitted - sudah dikirim QA untuk diperiksa.
 *   reviewed  - sudah diperiksa reviewer/supervisor.
 *   locked    - terkunci; perubahan normal tidak lagi diizinkan.
 *   cancelled - dibatalkan dan disertai alasan.
 */
class QaSamplingAssessment extends Model
{
    use HasFactory;

    protected $table = 'qa_sampling_assessments';

    protected $fillable = [
        'employee_id',
        'qa_id',
        'department_id',
        'scorecard_id',
        'scorecard_version_id',
        'channel_id',
        'employee_name_snapshot',
        'scorecard_name_snapshot',
        'version_number_snapshot',
        'channel_name_snapshot',
        'sample_link',
        'sample_id',
        'assessment_date',
        'period',
        'status',
        'initial_score',
        'penalty_total',
        'final_score',
        'zero_tolerance_triggered',
        'notes',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'locked_at',
        'cancelled_at',
        'cancelled_reason',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'assessment_date' => 'date',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'locked_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'initial_score' => 'decimal:2',
            'penalty_total' => 'decimal:2',
            'final_score' => 'decimal:2',
            'zero_tolerance_triggered' => 'boolean',
            'version_number_snapshot' => 'integer',
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Relasi                                                              */
    /* ------------------------------------------------------------------ */

    /** Karyawan yang dinilai. */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    /** QA yang menilai. */
    public function qa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qa_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function scorecard(): BelongsTo
    {
        return $this->belongsTo(Scorecard::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ScorecardVersion::class, 'scorecard_version_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QaSamplingItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /* ------------------------------------------------------------------ */
    /* Status                                                              */
    /* ------------------------------------------------------------------ */

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return ['draft', 'submitted', 'reviewed', 'locked', 'cancelled'];
    }

    /**
     * Label status dalam bahasa Indonesia, dipetakan sekali di sini.
     *
     * Dipakai accessor `status_label` (satu baris) DAN halaman yang hanya
     * punya nama status tanpa modelnya — mis. ringkasan jumlah per status di
     * dashboard HR. Tanpa peta ini, halaman seperti itu menulis ulang
     * terjemahannya sendiri dan cepat atau lambat berbeda dari tabel.
     *
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            'draft' => 'Draft',
            'submitted' => 'Terkirim',
            'reviewed' => 'Diperiksa',
            'locked' => 'Terkunci',
            'cancelled' => 'Dibatalkan',
        ];
    }

    /** Hanya DRAFT yang boleh disunting QA (PRD §16). */
    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    /** LOCKED & CANCELLED bersifat final; tidak ada transisi keluar. */
    public function isFinal(): bool
    {
        return in_array($this->status, ['locked', 'cancelled'], true);
    }

    public function isSubmitted(): bool
    {
        return in_array($this->status, ['submitted', 'reviewed'], true);
    }

    /**
     * Label status dalam bahasa Indonesia untuk tampilan.
     */
    public function getStatusLabelAttribute(): string
    {
        return self::statusLabels()[$this->status]
            ?? ucfirst((string) $this->status);
    }

    /**
     * Label sample: pakai sample_id bila ada, jika tidak tampilkan tautannya.
     */
    public function getSampleLabelAttribute(): string
    {
        return $this->sample_id ?: ($this->sample_link ?: '—');
    }

    /* ------------------------------------------------------------------ */
    /* Scope                                                               */
    /* ------------------------------------------------------------------ */

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['cancelled']);
    }
}
