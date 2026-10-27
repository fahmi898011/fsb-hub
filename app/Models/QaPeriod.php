<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Periode modul QA (PRD QA Assessment §7).
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, periode
 */
class QaPeriod extends Model
{
    use HasFactory;

    protected $table = 'qa_periods';

    protected $fillable = [
        'name',
        'slug',
        'start_date',
        'end_date',
        'parent_period_id',
        'status',
        'description',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(QaPeriod::class, 'parent_period_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(QaPeriod::class, 'parent_period_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(QaAssessment::class, 'period_id');
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
        return $query->where('status', 'active');
    }

    public function scopeCurrent($query)
    {
        return $query->where('start_date', '<=', now())
            ->where('end_date', '>=', now());
    }
}