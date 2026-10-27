<?php

namespace Database\Factories;

use App\Models\ExamForm;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory untuk form ujian QA Assessment.
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian B
 */
class ExamFormFactory extends Factory
{
    protected $model = ExamForm::class;

    public function definition(): array
    {
        return [
            'title' => fake()->unique()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'created_by' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'allow_retake' => fake()->boolean(30),
            'max_attempts' => fake()->optional()->numberBetween(1, 3),
            'duration_minutes' => fake()->optional()->randomElement([30, 45, 60, 90, 120]),
            'passing_score' => fake()->optional()->randomElement([60, 70, 75, 80]),
            'grading_method' => fake()->randomElement(['highest', 'latest', 'average']),
            'status' => 'draft',
            'published_at' => null,
            'updated_by' => User::inRandomOrder()->first()?->id ?? User::factory(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn() => [
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function withRetake(int $maxAttempts = 3): static
    {
        return $this->state(fn() => [
            'allow_retake' => true,
            'max_attempts' => $maxAttempts,
        ]);
    }
}