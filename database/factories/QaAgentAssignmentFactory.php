<?php

namespace Database\Factories;

use App\Models\QaAgentAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory untuk penugasan QA ke agent.
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian C
 */
class QaAgentAssignmentFactory extends Factory
{
    protected $model = QaAgentAssignment::class;

    public function definition(): array
    {
        $qa = User::inRandomOrder()->first();
        $agent = User::where('id', '!=', $qa?->id)->inRandomOrder()->first();
        $assignedBy = User::whereNotIn('id', [$qa?->id, $agent?->id])->inRandomOrder()->first();

        return [
            'qa_id' => $qa?->id ?? User::factory(),
            'agent_id' => $agent?->id ?? User::factory(),
            'assigned_by' => $assignedBy?->id ?? User::factory(),
            'active' => true,
            'notes' => fake()->optional()->sentence(),
            'created_by' => $assignedBy?->id ?? User::factory(),
            'updated_by' => $assignedBy?->id ?? User::factory(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn() => ['active' => false]);
    }

    public function forQa(int $qaId): static
    {
        return $this->state(fn() => ['qa_id' => $qaId]);
    }

    public function forAgent(int $agentId): static
    {
        return $this->state(fn() => ['agent_id' => $agentId]);
    }
}