<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jawaban agent pada ujian (PRD QA Assessment §9).
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian B
 *
 * Satu baris = jawaban satu soal dalam satu assignment.
 * `answer`: teks bebas untuk essay, JSON array label untuk multiple_choice.
 * `score`: nilainya (diisi QA saat grading). Essay = manual, multiple_choice = otomatis/override.
 * `grader_notes`: catatan penilai QA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_answers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('assignment_id')->constrained('exam_assignments')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('exam_form_questions')->cascadeOnDelete();

            $table->json('answer')->nullable();         // jawaban agent
            $table->decimal('score', 6, 2)->nullable(); // skor yg diberikan QA
            $table->text('grader_notes')->nullable();   // catatan QA

            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();

            $table->timestamps();

            $table->unique(['assignment_id', 'question_id'], 'exam_answers_unique_per_assignment');
            $table->index(['assignment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_answers');
    }
};