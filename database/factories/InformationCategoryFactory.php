<?php

namespace Database\Factories;

use App\Models\InformationCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InformationCategory>
 */
class InformationCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'icon' => 'book-open',
            'color' => fake()->randomElement(['#3b82f6', '#ef4444', '#8b5cf6', '#10b981', '#f59e0b']),
            'status' => 'active',
            'sort_order' => fake()->numberBetween(1, 100),
        ];
    }
}
