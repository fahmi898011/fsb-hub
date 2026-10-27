<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Tim ' . $this->faker->unique()->numberBetween(1, 9999);

        return [
            'department_id' => Department::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $this->faker->sentence(),
            'leader_id' => null,
            'status' => 'active',
        ];
    }

    /** Tim tanpa divisi, berguna untuk menguji tampilan tanpa divisi. */
    public function withoutDepartment(): static
    {
        return $this->state(fn () => ['department_id' => null]);
    }

    public function ledBy(User $leader): static
    {
        return $this->state(fn () => ['leader_id' => $leader->getKey()]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }
}
