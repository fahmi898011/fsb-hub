<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Header penilaian kualitatif (PRD QA Assessment §4).
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian A
 */
class QaAssessment extends Model
{
    use HasFactory;

    protected $table = 'qa_assessments';

    protected $fillable = [
        'agent_id',
        'qa_id',
        'period_id',
        'agent_name_snapshot',
        'qa_name_snapshot',
        'period_name_snapshot',
        'general_notes',
        'status',
        'published_at',
        'published_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function qa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qa_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(QaPeriod::class, 'period_id');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QaAssessmentItem::class, 'assessment_id')->orderBy('sort_order');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeForAgent($query, int $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    public function scopeForQa($query, int $qaId)
    {
        return $query->where('qa_id', $qaId);
    }

    public function scopeForPeriod($query, int $periodId)
    {
        return $query->where('period_id', $periodId);
    }
}