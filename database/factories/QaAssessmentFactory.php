<?php

namespace Database\Factories;

use App\Models\QaAssessment;
use App\Models\QaPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory untuk header penilaian kualitatif QA.
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment
 */
class QaAssessmentFactory extends Factory
{
    protected $model = QaAssessment::class;

    public function definition(): array
    {
        $agent = User::inRandomOrder()->first();
        $qa = User::where('id', '!=', $agent?->id)->inRandomOrder()->first();
        $period = QaPeriod::inRandomOrder()->first();

        return [
            'agent_id' => $agent?->id ?? User::factory(),
            'qa_id' => $qa?->id ?? User::factory(),
            'period_id' => $period?->id ?? QaPeriod::factory(),
            'agent_name_snapshot' => $agent?->name ?? fake()->name(),
            'qa_name_snapshot' => $qa?->name ?? fake()->name(),
            'period_name_snapshot' => $period?->name ?? fake()->words(3, true),
            'general_notes' => fake()->optional()->paragraph(),
            'status' => 'draft',
            'published_at' => null,
            'published_by' => null,
            'created_by' => $qa?->id ?? User::factory(),
            'updated_by' => $qa?->id ?? User::factory(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn() => [
            'status' => 'published',
            'published_at' => now(),
            'published_by' => $this->faker->randomElement([User::inRandomOrder()->first()?->id, null]),
        ]);
    }

    public function forAgent(int $agentId): static
    {
        return $this->state(fn() => ['agent_id' => $agentId]);
    }

    public function forQa(int $qaId): static
    {
        return $this->state(fn() => ['qa_id' => $qaId]);
    }

    public function forPeriod(int $periodId): static
    {
        return $this->state(fn() => ['period_id' => $periodId]);
    }
}