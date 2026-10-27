<?php

namespace Database\Factories;

use App\Models\ScorecardCategory;
use App\Models\ScorecardParameter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScorecardParameter>
 */
class ScorecardParameterFactory extends Factory
{
    protected $model = ScorecardParameter::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scorecard_category_id' => ScorecardCategory::factory(),
            'name' => $this->faker->unique()->words(3, true),
            'description' => null,
            'weight' => 5,
            'evaluation_type' => 'yes_no',
            'parameter_type' => 'normal',
            'ztp_effect' => 'zero_score',
            'answer_polarity' => 'normal',
            'guideline' => null,
            'penalty_rule' => null,
            'is_required' => true,
            'status' => 'active',
            'sort_order' => 0,
        ];
    }

    public function zeroTolerance(string $effect = 'zero_score', string $polarity = 'inverted'): static
    {
        return $this->state(fn () => [
            'parameter_type' => 'zero_tolerance',
            'ztp_effect' => $effect,
            'answer_polarity' => $polarity,
            'weight' => 0,
        ]);
    }
}
