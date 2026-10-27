<?php

namespace Database\Factories;

use App\Models\InformationCategory;
use App\Models\InformationItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InformationItem>
 */
class InformationItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => InformationCategory::factory(),
            'title' => fake()->unique()->sentence(4),
            'document_number' => 'DOC-'.fake()->unique()->numerify('#####'),
            'description' => fake()->paragraph(),
            'content' => fake()->paragraphs(2, true),
            'status' => 'draft',
            'published_at' => null,
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => 'draft',
            'published_at' => null,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'status' => 'archived',
        ]);
    }
}
