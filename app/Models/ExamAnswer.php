<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jawaban agent pada ujian QA Assessment (PRD QA Assessment §9).
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian B
 */
class ExamAnswer extends Model
{
    use HasFactory;

    protected $table = 'exam_answers';

    protected $fillable = [
        'assignment_id',
        'question_id',
        'answer',
        'score',
        'grader_notes',
        'graded_by',
        'graded_at',
    ];

    protected $casts = [
        'answer' => 'array',
        'score' => 'decimal:2',
        'graded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ExamAssignment::class, 'assignment_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ExamFormQuestion::class, 'question_id');
    }

    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}