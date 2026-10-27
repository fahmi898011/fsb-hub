<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bank soal (PRD Employee KPI/QA §21/§32).
 *
 * AGENT: asisten | 2026-10-05 | buat berkas baru | modul Quiz, fase 4 quiz
 *
 * Satu baris = satu soal. Ini adalah SUMBER soal, bukan soal yang sedang
 * dikerjakan. Quiz memakai salinannya lewat QuizVersionQuestion (PRD §23).
 */
class QuestionBank extends Model
{
    use HasFactory;

    protected $table = 'question_banks';

    protected $fillable = [
        'department_id',
        'question_category_id',
        'topic',
        'question',
        'question_type',
        'difficulty',
        'explanation',
        'point',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'point' => 'integer',
        ];
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(QuestionCategory::class, 'question_category_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Kunci jawaban: id opsi yang bertanda benar.
     *
     * @return list<int>
     */
    public function correctOptionIds(): array
    {
        return $this->options()->where('is_correct', true)->pluck('id')->all();
    }

    /**
     * @return list<string>
     */
    public static function questionTypes(): array
    {
        return ['single', 'multiple', 'true_false'];
    }

    /**
     * @return list<string>
     */
    public static function difficulties(): array
    {
        return ['easy', 'medium', 'hard'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->question_type) {
            'single' => 'Pilihan Ganda',
            'multiple' => 'Pilihan Ganda (banyak jawaban)',
            'true_false' => 'Benar/Salah',
            default => ucfirst((string) $this->question_type),
        };
    }

    public function getDifficultyLabelAttribute(): string
    {
        return match ($this->difficulty) {
            'easy' => 'Mudah',
            'medium' => 'Sedang',
            'hard' => 'Sulit',
            default => ucfirst((string) $this->difficulty),
        };
    }
}
