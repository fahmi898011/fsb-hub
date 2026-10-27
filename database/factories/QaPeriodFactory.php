<?php

namespace Database\Factories;

use App\Models\QaPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory untuk periode QA.
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, periode
 */
class QaPeriodFactory extends Factory
{
    protected $model = QaPeriod::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);
        $start = fake()->dateTimeBetween('-1 year', 'now');
        $end = fake()->dateTimeBetween($start, '+6 months');

        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . fake()->unique()->numberBetween(100, 999),
            'start_date' => $start,
            'end_date' => $end,
            'parent_period_id' => null,
            'status' => fake()->randomElement(['active', 'inactive']),
            'description' => fake()->optional()->paragraph(),
            'created_by' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'updated_by' => User::inRandomOrder()->first()?->id ?? User::factory(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn() => ['status' => 'active']);
    }

    public function current(): static
    {
        return $this->state(fn() => [
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'status' => 'active',
        ]);
    }

    public function withParent(?QaPeriod $parent = null): static
    {
        return $this->state(fn() => ['parent_period_id' => $parent?->id ?? QaPeriod::factory()]);
    }
}