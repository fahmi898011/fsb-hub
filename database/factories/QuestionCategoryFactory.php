<?php

namespace Database\Factories;

use App\Models\QuestionCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<QuestionCategory>
 *
 * AGENT: asisten | 2026-10-05 | buat berkas baru | modul Quiz, fase 4 quiz
 */
class QuestionCategoryFactory extends Factory
{
    protected $model = QuestionCategory::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'department_id' => null,
            'name' => ucfirst($name),
            'slug' => Str::slug($name).'-'.$this->faker->unique()->numberBetween(1, 99999),
            'description' => null,
            'sort_order' => 0,
            'status' => 'active',
        ];
    }
}
