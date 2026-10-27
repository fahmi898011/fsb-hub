<?php

namespace Database\Factories;

use App\Models\QuizAttempt;
use App\Models\QuizTemplate;
use App\Models\QuizVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizAttempt>
 *
 * AGENT: asisten | 2026-10-05 | buat berkas baru | modul Quiz, fase 4 quiz
 *
 * Default membuat satu attempt yang sudah SELESAI (pass) pada template+versi
 * baru, supaya test otorisasi/laporan tidak perlu menempuh alur pengerjaan.
 * State `pending()` untuk attempt yang belum dikumpulkan.
 */
class QuizAttemptFactory extends Factory
{
    protected $model = QuizAttempt::class;

    public function definition(): array
    {
        $template = QuizTemplate::factory()->active()->create();

        return [
            'employee_id' => \App\Models\User::factory(),
            'quiz_template_id' => $template->id,
            'quiz_version_id' => QuizVersion::factory()->create(['quiz_template_id' => $template->id])->id,
            'quiz_name_snapshot' => $template->name,
            'version_number_snapshot' => 1,
            'passing_score_snapshot' => 80,
            'started_at' => now()->subMinutes(10),
            'completed_at' => now(),
            'total_questions' => 1,
            'correct_count' => 1,
            'wrong_count' => 0,
            'total_point' => 1,
            'earned_point' => 1,
            'score' => 100,
            'result' => 'pass',
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'completed_at' => null,
            'correct_count' => 0,
            'wrong_count' => 0,
            'earned_point' => 0,
            'score' => null,
            'result' => 'pending',
        ]);
    }
}
