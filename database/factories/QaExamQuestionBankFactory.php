<?php

namespace Database\Factories;

use App\Models\QaAssessmentCategory;
use App\Models\QaExamQuestionBank;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory untuk bank soal ujian QA Assessment.
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian B
 */
class QaExamQuestionBankFactory extends Factory
{
    protected $model = QaExamQuestionBank::class;

    public function definition(): array
    {
        $type = fake()->randomElement([QaExamQuestionBank::TYPE_ESSAY, QaExamQuestionBank::TYPE_MULTIPLE_CHOICE]);
        $options = null;

        if ($type === QaExamQuestionBank::TYPE_MULTIPLE_CHOICE) {
            $choices = [];
            $correctIndex = fake()->numberBetween(0, 3);
            foreach (range('A', 'D') as $i => $label) {
                $choices[] = [
                    'label' => $label,
                    'text' => fake()->sentence(),
                    'is_correct' => $i === $correctIndex,
                ];
            }
            $options = $choices;
        }

        return [
            'category_id' => QaAssessmentCategory::inRandomOrder()->first()?->id ?? QaAssessmentCategory::factory(),
            'question_type' => $type,
            'question' => fake()->sentence(),
            'options' => $options,
            'point' => fake()->numberBetween(1, 10),
            'status' => fake()->randomElement(['active', 'inactive']),
            'created_by' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'updated_by' => User::inRandomOrder()->first()?->id ?? User::factory(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn() => ['status' => 'active']);
    }

    public function essay(): static
    {
        return $this->state(fn() => ['question_type' => QaExamQuestionBank::TYPE_ESSAY, 'options' => null]);
    }

    public function multipleChoice(): static
    {
        return $this->state(function () {
            $choices = [];
            $correctIndex = fake()->numberBetween(0, 3);
            foreach (range('A', 'D') as $i => $label) {
                $choices[] = [
                    'label' => $label,
                    'text' => fake()->sentence(),
                    'is_correct' => $i === $correctIndex,
                ];
            }
            return [
                'question_type' => QaExamQuestionBank::TYPE_MULTIPLE_CHOICE,
                'options' => $choices,
            ];
        });
    }
}