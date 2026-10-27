<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_number' => 'TIK-' . now()->format('Ymd') . '-' . $this->faker->unique()->numerify('#####'),
            'user_id' => User::factory(),
            'assigned_to' => null,
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'category' => $this->faker->randomElement(['hardware', 'software', 'network', 'account', 'other']),
            'priority' => $this->faker->randomElement(['low', 'medium', 'high', 'urgent']),
            'status' => 'open',
            'resolved_at' => null,
            'closed_at' => null,
        ];
    }

    /** Tiket yang sudah punya penanggung jawab. */
    public function assignedTo(User $agent): static
    {
        return $this->state(fn () => ['assigned_to' => $agent->getKey()]);
    }

    /** Tiket yang sedang dikerjakan, biasanya hasil penugasan. */
    public function inProgress(): static
    {
        return $this->state(fn () => ['status' => 'in_progress']);
    }

    public function resolved(): static
    {
        return $this->state(fn () => ['status' => 'resolved', 'resolved_at' => now()]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => 'closed',
            'resolved_at' => now()->subDay(),
            'closed_at' => now(),
        ]);
    }

    public function urgent(): static
    {
        return $this->state(fn () => ['priority' => 'urgent']);
    }
}
