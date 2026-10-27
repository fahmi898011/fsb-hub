<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkNote>
 *
 * AGENT: asisten | 2026-10-01 | buat berkas baru | modul Catatan Kerja, fase 1 backend
 */
class WorkNoteFactory extends Factory
{
    protected $model = WorkNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'body' => $this->faker->paragraph(3),
            'pinned' => false,
            'last_edited_at' => null,
            'lock_version' => 1,
        ];
    }

    /** Catatan kosong: hanya dibuat, belum diisi (mis. hasil tombol "Catatan Baru"). */
    public function empty(): static
    {
        return $this->state(fn () => [
            'title' => null,
            'body' => '',
            'last_edited_at' => null,
        ]);
    }

    /** Catatan yang sudah pernah disunting isinya. */
    public function edited(): static
    {
        return $this->state(fn () => [
            'last_edited_at' => now(),
            'lock_version' => 2,
        ]);
    }

    public function pinned(): static
    {
        return $this->state(fn () => ['pinned' => true]);
    }

    /** Catatan di tong sampah. */
    public function trashed(): static
    {
        return $this->state(fn () => ['deleted_at' => now()]);
    }
}
