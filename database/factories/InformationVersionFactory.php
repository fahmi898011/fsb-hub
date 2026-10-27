<?php

namespace Database\Factories;

use App\Models\InformationItem;
use App\Models\InformationVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InformationVersion>
 */
class InformationVersionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'information_id' => InformationItem::factory(),
            'version' => '1.0',
            'file_path' => 'information/'.now()->year.'/'.Str::uuid().'.pdf',
            'file_name' => fake()->word().'.pdf',
            'file_size' => fake()->numberBetween(100, 5000) * 1024,
            'mime_type' => 'application/pdf',
            'change_notes' => fake()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
