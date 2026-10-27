<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jawaban satu soal dalam sebuah pengerjaan (PRD Employee KPI/QA §33).
 *
 * AGENT: asisten | 2026-10-05 | buat berkas baru | modul Quiz, fase 4 quiz
 */
class QuizAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_attempt_id',
        'quiz_version_question_id',
        'question_snapshot',
        'selected_option_ids',
        'is_correct',
        'point_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'selected_option_ids' => 'array',
            'is_correct' => 'boolean',
            'point_snapshot' => 'integer',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id');
    }

    public function versionQuestion(): BelongsTo
    {
        return $this->belongsTo(QuizVersionQuestion::class, 'quiz_version_question_id');
    }
}
