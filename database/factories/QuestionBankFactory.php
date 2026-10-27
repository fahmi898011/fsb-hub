<?php

namespace Database\Factories;

use App\Models\QuestionBank;
use App\Models\QuestionCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionBank>
 *
 * AGENT: asisten | 2026-10-05 | buat berkas baru | modul Quiz, fase 4 quiz
 *
 * Default membuat soal pilihan ganda `single` dengan 4 opsi (A-D) dan A sebagai
 * kunci, supaya test penilaian dapat berjalan tanpa menyiapkan opsi manual.
 * Test yang menguji `multiple`/`true_false` memakai state di bawah.
 */
class QuestionBankFactory extends Factory
{
    protected $model = QuestionBank::class;

    public function definition(): array
    {
        return [
            'department_id' => null,
            'question_category_id' => QuestionCategory::factory(),
            'topic' => $this->faker->word(),
            'question' => $this->faker->sentence().'?',
            'question_type' => 'single',
            'difficulty' => 'medium',
            'explanation' => $this->faker->sentence(),
            'point' => 1,
            'status' => 'active',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (QuestionBank $question) {
            if ($question->options()->exists()) {
                return;
            }

            $labels = ['A', 'B', 'C', 'D'];

            foreach ($labels as $index => $label) {
                $question->options()->create([
                    'option_label' => $label,
                    'option_text' => 'Pilihan '.$label,
                    'is_correct' => $index === 0,
                    'sort_order' => $index,
                ]);
            }
        });
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }

    public function trueFalse(): static
    {
        return $this->state(fn () => ['question_type' => 'true_false'])->afterCreating(function (QuestionBank $question) {
            $question->options()->delete();
            $question->options()->createMany([
                ['option_label' => 'B', 'option_text' => 'Benar', 'is_correct' => true, 'sort_order' => 0],
                ['option_label' => 'S', 'option_text' => 'Salah', 'is_correct' => false, 'sort_order' => 1],
            ]);
        });
    }
}
