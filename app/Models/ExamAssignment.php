<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Distribusi form ujian ke agent (PRD QA Assessment §5).
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian B
 */
class ExamAssignment extends Model
{
    use HasFactory;

    protected $table = 'exam_assignments';

    protected $fillable = [
        'form_id',
        'agent_id',
        'assigned_by',
        'attempt_ke',
        'status',
        'started_at',
        'completed_at',
        'total_score',
        'max_score',
        'percentage',
        'result',
        'graded_by',
        'graded_at',
        'published_at',
        'published_by',
        'score_picked',
        'answer_sheet_path',
        'answer_sheet_uploaded_by',
        'answer_sheet_uploaded_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'attempt_ke' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'total_score' => 'decimal:2',
        'max_score' => 'decimal:2',
        'percentage' => 'decimal:2',
        'graded_at' => 'datetime',
        'published_at' => 'datetime',
        'answer_sheet_uploaded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(ExamForm::class, 'form_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    /** QA yang mengunggah lembar jawaban kertas. */
    public function answerSheetUploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answer_sheet_uploaded_by');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ExamAnswer::class, 'assignment_id');
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(QuestionUsageLog::class, 'exam_assignment_id');
    }

    public function scopeAssigned($query)
    {
        return $query->where('status', 'assigned');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    public function scopeGraded($query)
    {
        return $query->where('status', 'graded');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeForAgent($query, int $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    public function scopeForForm($query, int $formId)
    {
        return $query->where('form_id', $formId);
    }
}