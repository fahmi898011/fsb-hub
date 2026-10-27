<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\Kpi;
use App\Models\Scorecard;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Scorecard>
 */
class ScorecardFactory extends Factory
{
    protected $model = Scorecard::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $seq = $this->faker->unique()->numberBetween(1, 99999);
        $name = "Scorecard {$seq}";

        return [
            'kpi_id' => Kpi::factory(),
            'channel_id' => Channel::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $this->faker->sentence(),
            'status' => 'active',
            'sort_order' => 0,
        ];
    }
}
