<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pengerjaan quiz oleh karyawan (PRD Employee KPI/QA §27/§33).
 *
 * AGENT: asisten | 2026-10-05 | buat berkas baru | modul Quiz, fase 4 quiz
 *
 * Satu baris = satu kali mengerjakan. Angka hasil disimpan final saat submit.
 */
class QuizAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'quiz_version_id',
        'quiz_template_id',
        'quiz_name_snapshot',
        'version_number_snapshot',
        'passing_score_snapshot',
        'started_at',
        'completed_at',
        'total_questions',
        'correct_count',
        'wrong_count',
        'total_point',
        'earned_point',
        'score',
        'result',
        'period',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'total_questions' => 'integer',
            'correct_count' => 'integer',
            'wrong_count' => 'integer',
            'total_point' => 'decimal:2',
            'earned_point' => 'decimal:2',
            'score' => 'decimal:2',
            'passing_score_snapshot' => 'decimal:2',
            'version_number_snapshot' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(QuizVersion::class, 'quiz_version_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(QuizTemplate::class, 'quiz_template_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class);
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function isPassed(): bool
    {
        return $this->result === 'pass';
    }

    public function getResultLabelAttribute(): string
    {
        return match ($this->result) {
            'pass' => 'LULUS',
            'fail' => 'TIDAK LULUS',
            'pending' => 'Belum Selesai',
            default => ucfirst((string) $this->result),
        };
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereNotNull('completed_at');
    }

    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }
}
