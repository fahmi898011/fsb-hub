<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => Conversation::TYPE_CHANNEL,
            'name' => 'Kanal ' . $this->faker->unique()->words(2, true),
            'description' => $this->faker->sentence(),
            'is_private' => false,
            'archived_at' => null,
            'created_by' => User::factory(),
        ];
    }

    /** DM tanpa nama; anggotanya diisi lewat `hasAttached`. */
    public function direct(): static
    {
        return $this->state(fn () => [
            'type' => Conversation::TYPE_DIRECT,
            'name' => null,
            'description' => null,
        ]);
    }

    public function private(): static
    {
        return $this->state(fn () => ['is_private' => true]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['archived_at' => now()]);
    }
}
