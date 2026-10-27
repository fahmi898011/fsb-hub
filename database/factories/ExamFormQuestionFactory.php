<?php

namespace Database\Factories;

use App\Models\ExamForm;
use App\Models\ExamFormQuestion;
use App\Models\QaExamQuestionBank;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory untuk soal dalam form ujian.
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian B
 */
class ExamFormQuestionFactory extends Factory
{
    protected $model = ExamFormQuestion::class;

    public function definition(): array
    {
        $bank = QaExamQuestionBank::inRandomOrder()->first() ?? QaExamQuestionBank::factory();

        return [
            'form_id' => ExamForm::inRandomOrder()->first()?->id ?? ExamForm::factory(),
            'question_bank_id' => $bank instanceof QaExamQuestionBank ? $bank->id : $bank,
            'sort_order' => fake()->numberBetween(0, 100),
            'point' => fake()->numberBetween(1, 10),
            'question_type_snapshot' => $bank instanceof QaExamQuestionBank ? $bank->question_type : QaExamQuestionBank::TYPE_ESSAY,
            'question_snapshot' => $bank instanceof QaExamQuestionBank ? $bank->question : fake()->sentence(),
            'options_snapshot' => $bank instanceof QaExamQuestionBank ? $bank->options : null,
            'point_snapshot' => $bank instanceof QaExamQuestionBank ? $bank->point : 1,
        ];
    }
}