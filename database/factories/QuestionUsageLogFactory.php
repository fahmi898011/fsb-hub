<?php

namespace Database\Factories;

use App\Models\ExamAssignment;
use App\Models\ExamForm;
use App\Models\QaExamQuestionBank;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory untuk log pemakaian soal.
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian B
 */
class QuestionUsageLogFactory extends Factory
{
    protected $model = \App\Models\QuestionUsageLog::class;

    public function definition(): array
    {
        return [
            'question_bank_id' => QaExamQuestionBank::inRandomOrder()->first()?->id ?? QaExamQuestionBank::factory(),
            'exam_form_id' => ExamForm::inRandomOrder()->first()?->id ?? ExamForm::factory(),
            'exam_assignment_id' => ExamAssignment::inRandomOrder()->first()?->id ?? ExamAssignment::factory(),
            'qa_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'agent_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'used_at' => now(),
        ];
    }
}