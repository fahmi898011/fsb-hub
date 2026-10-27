<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Versi quiz (PRD Employee KPI/QA §23/§33).
 *
 * AGENT: asisten | 2026-10-05 | buat berkas baru | modul Quiz, fase 4 quiz
 *
 * Versi membekukan daftar soal + aturan penilaian. Setelah dipakai attempt,
 * versi tidak boleh diubah lagi (PRD §23). Penjagaan itu ada di
 * QuizVersionPolicy/Service, bukan di model.
 */
class QuizVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_template_id',
        'version',
        'question_count',
        'passing_score',
        'time_limit',
        'effective_from',
        'effective_to',
        'status',
        'notes',
        'published_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'question_count' => 'integer',
            'passing_score' => 'decimal:2',
            'time_limit' => 'integer',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'published_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(QuizTemplate::class, 'quiz_template_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuizVersionQuestion::class)->orderBy('sort_order')->orderBy('id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /** Versi yang sudah dipakai minimal satu attempt tidak boleh diubah. */
    public function isLocked(): bool
    {
        return $this->attempts()->exists();
    }

    /** Total poin seluruh soal pada versi ini. */
    public function totalPoint(): int
    {
        return (int) $this->questions()->sum('point_snapshot');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
