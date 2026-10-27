<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Template quiz (PRD Employee KPI/QA §22/§33).
 *
 * AGENT: asisten | 2026-10-05 | buat berkas baru | modul Quiz, fase 4 quiz
 *
 * Template adalah "jenis quiz" (mis. Knowledge Assessment - Customer Service).
 * Daftar soal & aturan penilaian yang berlaku ada di versinya (QuizVersion).
 */
class QuizTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id',
        'name',
        'slug',
        'description',
        'question_count',
        'passing_score',
        'time_limit',
        'selection_method',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'question_count' => 'integer',
            'passing_score' => 'decimal:2',
            'time_limit' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $template) {
            if (empty($template->slug)) {
                $template->slug = Str::slug($template->name);
            }
        });
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(QuizVersion::class)->orderByDesc('version');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /**
     * Versi yang saat ini berlaku: versi `active` dengan nomor tertinggi.
     */
    public function activeVersion(): ?QuizVersion
    {
        return $this->versions()
            ->where('status', 'active')
            ->orderByDesc('version')
            ->first();
    }

    /**
     * @return list<string>
     */
    public static function selectionMethods(): array
    {
        return ['manual', 'random', 'category'];
    }

    public function getSelectionMethodLabelAttribute(): string
    {
        return match ($this->selection_method) {
            'manual' => 'Manual (QA memilih)',
            'random' => 'Acak dari bank soal',
            'category' => 'Per kategori',
            default => ucfirst((string) $this->selection_method),
        };
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
