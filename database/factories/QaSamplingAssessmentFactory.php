<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\QaSamplingAssessment>
 */
class QaSamplingAssessmentFactory extends Factory
{
    protected $model = \App\Models\QaSamplingAssessment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => User::factory(),
            'qa_id' => User::factory(),
            'department_id' => null,
            'scorecard_id' => null,
            'scorecard_version_id' => null,
            'channel_id' => null,
            'employee_name_snapshot' => $this->faker->name(),
            'scorecard_name_snapshot' => 'QA Scorecard '.$this->faker->word(),
            'version_number_snapshot' => 1,
            'channel_name_snapshot' => 'Inbound',
            'sample_link' => $this->faker->url(),
            'sample_id' => $this->faker->unique()->numerify('SMP-####'),
            'assessment_date' => now()->toDateString(),
            'period' => now()->format('Y-\WW'),
            'status' => 'draft',
            'initial_score' => 100,
            'penalty_total' => 0,
            'final_score' => 100,
            'zero_tolerance_triggered' => false,
            'notes' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => ['status' => 'submitted', 'submitted_at' => now()]);
    }

    public function reviewed(): static
    {
        return $this->state(fn () => ['status' => 'reviewed', 'submitted_at' => now(), 'reviewed_at' => now()]);
    }

    public function locked(): static
    {
        return $this->state(fn () => [
            'status' => 'locked',
            'submitted_at' => now(),
            'reviewed_at' => now(),
            'locked_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_reason' => 'Dibatalkan saat pengujian.',
        ]);
    }
}
