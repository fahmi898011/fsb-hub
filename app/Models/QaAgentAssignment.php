<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penugasan QA ke agent (PRD QA Assessment §6).
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian C
 */
class QaAgentAssignment extends Model
{
    use HasFactory;

    protected $table = 'qa_agent_assignments';

    protected $fillable = [
        'qa_id',
        'agent_id',
        'assigned_by',
        'active',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function qa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qa_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeForQa($query, int $qaId)
    {
        return $query->where('qa_id', $qaId);
    }

    public function scopeForAgent($query, int $agentId)
    {
        return $query->where('agent_id', $agentId);
    }
}