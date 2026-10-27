<?php

namespace Database\Factories;

use App\Models\Scorecard;
use App\Models\ScorecardVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScorecardVersion>
 */
class ScorecardVersionFactory extends Factory
{
    protected $model = ScorecardVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scorecard_id' => Scorecard::factory(),
            'version' => 1,
            'status' => 'active',
            'effective_from' => now()->toDateString(),
            'effective_to' => null,
            'notes' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft']);
    }
}
