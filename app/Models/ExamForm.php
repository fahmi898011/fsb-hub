<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Form ujian tertulis QA Assessment (PRD QA Assessment §5).
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian B
 */
class ExamForm extends Model
{
    use HasFactory;

    protected $table = 'exam_forms';

    protected $fillable = [
        'title',
        'description',
        'created_by',
        'allow_retake',
        'max_attempts',
        'duration_minutes',
        'passing_score',
        'grading_method',
        'status',
        'published_at',
        'updated_by',
    ];

    protected $casts = [
        'allow_retake' => 'boolean',
        'max_attempts' => 'integer',
        'duration_minutes' => 'integer',
        'passing_score' => 'decimal:2',
        'published_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ExamFormQuestion::class, 'form_id')->orderBy('sort_order');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ExamAssignment::class, 'form_id');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function getTotalPointsAttribute(): int
    {
        return $this->questions->sum('point');
    }

    /**
     * Tempel sebuah soal bank ke form ini: urutan paling akhir + snapshot isi soal.
     *
     * Dipakai dua jalur sekaligus (tulis soal di form & ambil soal dari bank),
     * supaya bentuk snapshot-nya selalu sama.
     *
     * AGENT: asisten | 2026-10-20 | tambah helper | modul QA Assessment, bank soal
     */
    public function attachQuestion(QaExamQuestionBank $bank, ?int $point = null): ExamFormQuestion
    {
        return $this->questions()->create([
            'question_bank_id' => $bank->id,
            'sort_order' => ($this->questions()->max('sort_order') ?? 0) + 1,
            'point' => $point ?? $bank->point,
            'question_type_snapshot' => $bank->question_type,
            'question_snapshot' => $bank->question,
            'options_snapshot' => $bank->options,
            'point_snapshot' => $bank->point,
        ]);
    }
}
