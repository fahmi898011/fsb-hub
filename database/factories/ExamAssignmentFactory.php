<?php

namespace Database\Factories;

use App\Models\ExamAssignment;
use App\Models\ExamForm;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory untuk assignment ujian ke agent.
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian B
 */
class ExamAssignmentFactory extends Factory
{
    protected $model = ExamAssignment::class;

    public function definition(): array
    {
        $form = ExamForm::inRandomOrder()->first() ?? ExamForm::factory();
        $agent = User::inRandomOrder()->first();
        $assignedBy = User::where('id', '!=', $agent?->id)->inRandomOrder()->first();

        return [
            'form_id' => $form instanceof ExamForm ? $form->id : $form,
            'agent_id' => $agent?->id ?? User::factory(),
            'assigned_by' => $assignedBy?->id ?? User::factory(),
            'attempt_ke' => 1,
            'status' => 'assigned',
            'started_at' => null,
            'completed_at' => null,
            'total_score' => null,
            'max_score' => null,
            'percentage' => null,
            'result' => null,
            'graded_by' => null,
            'graded_at' => null,
            'published_at' => null,
            'published_by' => null,
            'score_picked' => null,
            'created_by' => $assignedBy?->id ?? User::factory(),
            'updated_by' => $assignedBy?->id ?? User::factory(),
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn() => [
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    public function submitted(): static
    {
        return $this->state(fn() => [
            'status' => 'submitted',
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);
    }

    public function graded(float $percentage = 85): static
    {
        return $this->state(fn() => [
            'status' => 'graded',
            'started_at' => now()->subHour(),
            'completed_at' => now(),
            'total_score' => $percentage,
            'max_score' => 100,
            'percentage' => $percentage,
            'result' => $percentage >= 70 ? 'pass' : 'fail',
            'graded_by' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'graded_at' => now(),
        ]);
    }

    public function published(float $percentage = 85): static
    {
        return $this->graded($percentage)->state(fn() => [
            'status' => 'published',
            'published_at' => now(),
            'published_by' => User::inRandomOrder()->first()?->id ?? User::factory(),
        ]);
    }

    public function forAgent(int $agentId): static
    {
        return $this->state(fn() => ['agent_id' => $agentId]);
    }

    public function forForm(int $formId): static
    {
        return $this->state(fn() => ['form_id' => $formId]);
    }
}