<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log pemakaian soal bank (PRD QA Assessment §5).
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian B
 */
class QuestionUsageLog extends Model
{
    use HasFactory;

    protected $table = 'question_usage_log';

    protected $fillable = [
        'question_bank_id',
        'exam_form_id',
        'exam_assignment_id',
        'qa_id',
        'agent_id',
        'used_at',
    ];

    protected $casts = [
        'used_at' => 'datetime',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class, 'question_bank_id');
    }

    public function examForm(): BelongsTo
    {
        return $this->belongsTo(ExamForm::class, 'exam_form_id');
    }

    public function examAssignment(): BelongsTo
    {
        return $this->belongsTo(ExamAssignment::class, 'exam_assignment_id');
    }

    public function qa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qa_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}