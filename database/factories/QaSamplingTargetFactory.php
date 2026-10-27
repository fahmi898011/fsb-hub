<?php

namespace Database\Factories;

use App\Models\QaSamplingTarget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QaSamplingTarget>
 *
 * AGENT: asisten | 2026-10-04 | buat berkas baru | modul KPI, fase 3 QA reporting
 *
 * Default sengaja dibuat sebagai aturan UMUM (tanpa divisi/scorecard) yang
 * sudah berlaku sejak awal tahun, supaya test laporan tidak perlu menyiapkan
 * master apa pun lebih dulu. Test yang menguji aturan berskop menimpa
 * `department_id`/`scorecard_id` secara eksplisit.
 */
class QaSamplingTargetFactory extends Factory
{
    protected $model = QaSamplingTarget::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => null,
            'scorecard_id' => null,
            'frequency' => 'weekly',
            'target_type' => 'per_employee',
            'target_value' => 5,
            'effective_date' => now()->startOfYear()->toDateString(),
            'status' => 'active',
            'notes' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }

    /** Aturan yang baru berlaku besok — dipakai menguji "belum efektif". */
    public function future(): static
    {
        return $this->state(fn () => ['effective_date' => now()->addDay()->toDateString()]);
    }

    public function forDivision(\App\Models\Department $division): static
    {
        return $this->state(fn () => ['department_id' => $division->id]);
    }

    public function forScorecard(\App\Models\Scorecard $scorecard): static
    {
        return $this->state(fn () => ['scorecard_id' => $scorecard->id]);
    }
}
