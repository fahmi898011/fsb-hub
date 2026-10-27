<?php

namespace Database\Factories;

use App\Models\QuizTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<QuizTemplate>
 *
 * AGENT: asisten | 2026-10-05 | buat berkas baru | modul Quiz, fase 4 quiz
 */
class QuizTemplateFactory extends Factory
{
    protected $model = QuizTemplate::class;

    public function definition(): array
    {
        $name = 'Knowledge Assessment '.$this->faker->unique()->numberBetween(1, 99999);

        return [
            'department_id' => null,
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => null,
            'question_count' => 5,
            'passing_score' => 80,
            'time_limit' => 30,
            'selection_method' => 'manual',
            'status' => 'draft',
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active']);
    }
}
