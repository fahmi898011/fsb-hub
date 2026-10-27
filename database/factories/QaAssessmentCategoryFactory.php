<?php

namespace Database\Factories;

use App\Models\QaAssessmentCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory untuk kategori penilaian QA.
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment
 */
class QaAssessmentCategoryFactory extends Factory
{
    protected $model = QaAssessmentCategory::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . fake()->unique()->numberBetween(100, 999),
            'description' => fake()->optional()->sentence(),
            'sort_order' => fake()->numberBetween(0, 100),
            'status' => fake()->randomElement(['active', 'inactive']),
            'created_by' => User::inRandomOrder()->first()?->id,
            'updated_by' => User::inRandomOrder()->first()?->id,
        ];
    }

    public function active(): static
    {
        return $this->state(fn() => ['status' => 'active']);
    }
}