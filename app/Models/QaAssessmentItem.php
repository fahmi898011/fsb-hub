<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Item penilaian per kategori (PRD QA Assessment §4).
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian A
 */
class QaAssessmentItem extends Model
{
    use HasFactory;

    protected $table = 'qa_assessment_items';

    protected $fillable = [
        'assessment_id',
        'category_id',
        'strengths',
        'weaknesses',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(QaAssessment::class, 'assessment_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(QaAssessmentCategory::class, 'category_id');
    }
}