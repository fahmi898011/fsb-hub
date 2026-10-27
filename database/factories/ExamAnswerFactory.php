<?php

namespace Database\Factories;

use App\Models\ExamAnswer;
use App\Models\ExamAssignment;
use App\Models\ExamFormQuestion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory untuk jawaban ujian.
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian B
 */
class ExamAnswerFactory extends Factory
{
    protected $model = ExamAnswer::class;

    public function definition(): array
    {
        $question = ExamFormQuestion::inRandomOrder()->first() ?? ExamFormQuestion::factory();
        $assignment = ExamAssignment::inRandomOrder()->first() ?? ExamAssignment::factory();

        $answer = null;
        if ($question instanceof ExamFormQuestion) {
            if ($question->question_type_snapshot === 'multiple_choice') {
                $correctLabels = collect($question->options_snapshot ?? [])
                    ->where('is_correct', true)
                    ->pluck('label')
                    ->all();
                $answer = ['selected' => $correctLabels];
            } else {
                $answer = ['value' => fake()->paragraph()];
            }
        }

        return [
            'assignment_id' => $assignment instanceof ExamAssignment ? $assignment->id : $assignment,
            'question_id' => $question instanceof ExamFormQuestion ? $question->id : $question,
            'answer' => $answer,
            'score' => null,
            'grader_notes' => null,
            'graded_by' => null,
            'graded_at' => null,
        ];
    }

    public function graded(float $score, ?string $notes = null): static
    {
        return $this->state(fn() => [
            'score' => $score,
            'grader_notes' => $notes ?? fake()->optional()->sentence(),
            'graded_by' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'graded_at' => now(),
        ]);
    }

    public function forAssignment(int $assignmentId): static
    {
        return $this->state(fn() => ['assignment_id' => $assignmentId]);
    }

    public function forQuestion(int $questionId): static
    {
        return $this->state(fn() => ['question_id' => $questionId]);
    }
}