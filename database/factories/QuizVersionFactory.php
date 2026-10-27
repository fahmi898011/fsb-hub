<?php

namespace Database\Factories;

use App\Models\QuizTemplate;
use App\Models\QuizVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizVersion>
 *
 * AGENT: asisten | 2026-10-05 | buat berkas baru | modul Quiz, fase 4 quiz
 */
class QuizVersionFactory extends Factory
{
    protected $model = QuizVersion::class;

    public function definition(): array
    {
        return [
            'quiz_template_id' => QuizTemplate::factory(),
            'version' => 1,
            'question_count' => 0,
            'passing_score' => 80,
            'time_limit' => 30,
            'effective_from' => now()->toDateString(),
            'effective_to' => null,
            'status' => 'draft',
            'notes' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active', 'published_at' => now()]);
    }
}
