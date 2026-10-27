<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Soal yang dipakai pada sebuah versi quiz (PRD Employee KPI/QA §23/§33).
 *
 * AGENT: asisten | 2026-10-05 | buat berkas baru | modul Quiz, fase 4 quiz
 *
 * Menyimpan SALINAN soal + poin saat versi dibekukan (snapshot), sehingga
 * perubahan bank soal tidak mengubah quiz yang sudah terbit (PRD §11/§23).
 */
class QuizVersionQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_version_id',
        'question_bank_id',
        'question_snapshot',
        'question_type_snapshot',
        'options_snapshot',
        'point_snapshot',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'point_snapshot' => 'integer',
            'options_snapshot' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(QuizVersion::class, 'quiz_version_id');
    }

    /**
     * Soal asal di bank. Boleh null bila soal bank sudah dihapus; salinan
     * teksnya tetap ada di `question_snapshot`.
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class, 'question_bank_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class, 'quiz_version_question_id');
    }
}
