<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $base = $this->faker->randomElement([
            'Human Resources', 'Finance', 'Business Development',
            'Information Technology', 'Quality Assurance', 'Marketing',
        ]);

        // Nomor unik dipakai agar pembuatan banyak divisi sekaligus (mis. untuk
        // menguji pagination) tidak menabrak unique constraint pada `name` dan
        // `code`. Catatan: jangan memakai unique()->randomElement() di sini —
        // daftarnya hanya 6 dan akan habis setelah 6 pemanggilan.
        $seq = $this->faker->unique()->numberBetween(1, 99999);

        $name = "{$base} {$seq}";

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'code' => 'D' . $seq,
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
