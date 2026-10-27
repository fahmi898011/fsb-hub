<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Kpi;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Kpi>
 */
class KpiFactory extends Factory
{
    protected $model = Kpi::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $seq = $this->faker->unique()->numberBetween(1, 99999);
        $name = "Quality Score {$seq}";

        return [
            'department_id' => Department::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'code' => 'KPI' . $seq,
            'description' => $this->faker->sentence(),
            'status' => 'active',
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }
}
