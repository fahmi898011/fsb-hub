<?php

namespace Database\Factories;

use App\Models\ScorecardCategory;
use App\Models\ScorecardVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScorecardCategory>
 */
class ScorecardCategoryFactory extends Factory
{
    protected $model = ScorecardCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scorecard_version_id' => ScorecardVersion::factory(),
            'name' => $this->faker->randomElement([
                'Opening & Closing', 'Probing & Research', 'Resolution / Accuracy', 'Communication',
            ]),
            'description' => null,
            'status' => 'active',
            'sort_order' => 0,
        ];
    }
}
