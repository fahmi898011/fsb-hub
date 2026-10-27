<?php

namespace Database\Factories;

use App\Models\QaAssessment;
use App\Models\QaAssessmentCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory untuk item penilaian per kategori.
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment
 */
class QaAssessmentItemFactory extends Factory
{
    protected $model = \App\Models\QaAssessmentItem::class;

    public function definition(): array
    {
        return [
            'assessment_id' => QaAssessment::inRandomOrder()->first()?->id ?? QaAssessment::factory(),
            'category_id' => QaAssessmentCategory::inRandomOrder()->first()?->id ?? QaAssessmentCategory::factory(),
            'strengths' => fake()->optional()->paragraph(),
            'weaknesses' => fake()->optional()->paragraph(),
            'sort_order' => fake()->numberBetween(0, 50),
        ];
    }
}