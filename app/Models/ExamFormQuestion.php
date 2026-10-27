<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Soal dalam form ujian QA Assessment (PRD QA Assessment §9).
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian B
 *
 * Satu baris = satu soal yang dipakai sebuah form. Baris inilah yang menyimpan
 * urutan, poin per form, dan snapshot isi soal. Sumber soalnya ada di
 * `question_bank` dan boleh dipakai form lain juga.
 */
class ExamFormQuestion extends Model
{
    use HasFactory;

    /**
     * Jenis soal yang disalin dari bank soal saat ditempel ke form.
     *
     * Nilainya SAMA dengan `QaExamQuestionBank::TYPE_*`, tetapi konstantanya
     * harus ada di sini juga: layanan penilaian membandingkan
     * `question_type_snapshot` dengan konstanta milik model ini. Tanpa
     * konstanta ini, `QaAssessmentScoringService::gradeAssignment()` melempar
     * "Undefined constant" dan tombol "Kumpulkan Jawaban" berakhir 500.
     *
     * AGENT: asisten | 2026-10-20 | konstanta jenis soal snapshot | modul QA Assessment
     */
    public const TYPE_ESSAY = 'essay';

    public const TYPE_MULTIPLE_CHOICE = 'multiple_choice';

    protected $table = 'exam_form_questions';

    protected $fillable = [
        'form_id',
        'question_bank_id',
        'sort_order',
        'point',
        'question_type_snapshot',
        'question_snapshot',
        'options_snapshot',
        'point_snapshot',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'point' => 'integer',
        'point_snapshot' => 'integer',
        'options_snapshot' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(ExamForm::class, 'form_id');
    }

    public function questionBank(): BelongsTo
    {
        return $this->belongsTo(QaExamQuestionBank::class, 'question_bank_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ExamAnswer::class, 'question_id');
    }
}
