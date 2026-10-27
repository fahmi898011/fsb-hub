<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bank soal ujian tertulis QA Assessment (PRD QA Assessment §5).
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian B
 *
 * Tabel: question_bank (singular, per PRD §9).
 * Berbeda dengan QuestionBank (plural) milik Quiz.
 *
 * Sejak 2026-10-20 tabel ini murni kolam soal bersama: tidak ada kolom
 * `form_id`. Soal dipakai sebuah form lewat pivot `exam_form_questions`,
 * sehingga satu soal boleh dipakai di banyak form.
 */
class QaExamQuestionBank extends Model
{
    use HasFactory;

    protected $table = 'question_bank';

    protected $fillable = [
        'category_id',
        'question_type',
        'question',
        'options',
        'point',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'options' => 'array',
        'point' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const TYPE_ESSAY = 'essay';

    public const TYPE_MULTIPLE_CHOICE = 'multiple_choice';

    public function category(): BelongsTo
    {
        return $this->belongsTo(QaAssessmentCategory::class, 'category_id');
    }

    /** Baris pemakaian soal ini di form-form ujian. */
    public function formQuestions(): HasMany
    {
        return $this->hasMany(ExamFormQuestion::class, 'question_bank_id');
    }

    /**
     * Form-form yang memakai soal ini (banyak-ke-banyak lewat pivot).
     *
     * AGENT: asisten | 2026-10-20 | tambah relasi | modul QA Assessment, bank soal
     */
    public function forms(): BelongsToMany
    {
        return $this->belongsToMany(ExamForm::class, 'exam_form_questions', 'question_bank_id', 'form_id')
            ->withPivot(['sort_order', 'point'])
            ->withTimestamps();
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(QuestionUsageLog::class, 'question_bank_id');
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

    /**
     * Soal yang sudah dipakai sebuah form.
     *
     * AGENT: asisten | 2026-10-20 | ganti scope kepemilikan jadi scope pemakaian | modul QA Assessment, bank soal
     */
    public function scopeUsedByForm($query, int $formId)
    {
        return $query->whereHas('formQuestions', fn ($q) => $q->where('form_id', $formId));
    }

    /** Soal yang belum dipakai form mana pun (masih menganggur di bank). */
    public function scopeUnused($query)
    {
        return $query->doesntHave('formQuestions');
    }

    public function scopeEssay($query)
    {
        return $query->where('question_type', self::TYPE_ESSAY);
    }

    public function scopeMultipleChoice($query)
    {
        return $query->where('question_type', self::TYPE_MULTIPLE_CHOICE);
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->question_type) {
            self::TYPE_ESSAY => 'Essay',
            self::TYPE_MULTIPLE_CHOICE => 'Pilihan Ganda',
            default => ucfirst((string) $this->question_type),
        };
    }
}
