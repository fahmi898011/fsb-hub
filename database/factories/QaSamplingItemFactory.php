<?php

namespace Database\Factories;

use App\Models\QaSamplingAssessment;
use App\Models\QaSamplingItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QaSamplingItem>
 */
class QaSamplingItemFactory extends Factory
{
    protected $model = QaSamplingItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'qa_sampling_assessment_id' => QaSamplingAssessment::factory(),
            'scorecard_parameter_id' => null,
            'category_name_snapshot' => 'Opening & Closing',
            'parameter_name_snapshot' => $this->faker->unique()->words(2, true),
            'weight_snapshot' => 5,
            'parameter_type_snapshot' => 'normal',
            'ztp_effect_snapshot' => 'zero_score',
            'answer_polarity_snapshot' => 'normal',
            'is_required_snapshot' => true,
            'answer' => null,
            'is_violation' => false,
            'penalty' => 0,
            'comment' => null,
            'sort_order' => 0,
        ];
    }

    /** Parameter normal dengan jawaban tertentu (belum dihitung). */
    public function answered(string $answer, float $weight = 5): static
    {
        return $this->state(fn () => [
            'answer' => $answer,
            'weight_snapshot' => $weight,
        ]);
    }

    /** ZTP terbalik: YES = pelanggaran (mis. "apakah terjadi pelanggaran?"). */
    public function zeroTolerance(string $effect = 'zero_score'): static
    {
        return $this->state(fn () => [
            'parameter_type_snapshot' => 'zero_tolerance',
            'ztp_effect_snapshot' => $effect,
            'answer_polarity_snapshot' => 'inverted',
            'weight_snapshot' => 0,
        ]);
    }
}
