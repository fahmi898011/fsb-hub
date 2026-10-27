<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bank soal / Question Bank (PRD Employee KPI/QA §21/§32).
 *
 * AGENT: asisten | 2026-10-05 | buat berkas baru | modul Quiz, fase 4 quiz
 *
 * Satu baris = satu soal. Soal ini adalah SUMBER; soal tidak pernah dipakai
 * langsung oleh sebuah quiz. Quiz memakai salinan lewat `quiz_version_questions`
 * (lihat migration itu), sehingga mengubah/menghapus soal di bank tidak
 * mengubah quiz yang sudah pernah dikerjakan (PRD §23).
 *
 * Jenis soal (PRD §21 "Question Type"):
 *   single   - pilihan ganda, satu jawaban benar.
 *   multiple - pilihan ganda, banyak jawaban benar.
 *   true_false - pernyataan benar/salah.
 *
 * `question_banks` (jamak) dipilih mengikuti konvensi Eloquent. Modul penilaian
 * kualitatif "PRD QA Assessment" memakai `question_bank` (tunggal) untuk ujian
 * tertulisnya — nama berbeda agar dua modul tidak berbagi tabel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_banks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('question_category_id')->nullable()->constrained('question_categories')->nullOnDelete();

            $table->string('topic')->nullable();
            $table->text('question');
            $table->enum('question_type', ['single', 'multiple', 'true_false'])->default('single');
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->default('medium');
            $table->text('explanation')->nullable();

            // Poin default soal. Bila soal dipakai pada quiz, poin dapat
            // "dibekukan" (point_snapshot) di `quiz_version_questions`.
            $table->unsignedInteger('point')->default(1);

            $table->enum('status', ['active', 'inactive'])->default('active');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'question_type']);
            $table->index('department_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_banks');
    }
};
