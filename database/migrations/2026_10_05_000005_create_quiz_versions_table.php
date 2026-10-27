<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versi quiz (PRD Employee KPI/QA §23/§33).
 *
 * AGENT: asisten | 2026-10-05 | buat berkas baru | modul Quiz, fase 4 quiz
 *
 * Versi membekukan DAFTAR SOAL dan aturan penilaian pada satu titik waktu. Bila
 * bank soal atau bobot berubah, Admin membuat versi baru; quiz yang sudah
 * dikerjakan tetap memakai versi lamanya (PRD §23: "Quiz yang sudah dikerjakan
 * tidak berubah ketika question bank diperbarui").
 *
 * `question_count`, `passing_score`, dan `time_limit` DISALIN dari template ke
 * versi (bukan hanya dibaca dari template) supaya perubahan template tidak
 * mengubah hasil quiz lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_versions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('quiz_template_id')->constrained('quiz_templates')->cascadeOnDelete();

            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('question_count')->default(10);
            $table->decimal('passing_score', 5, 2)->default(80);
            $table->unsignedInteger('time_limit')->nullable();

            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();

            $table->enum('status', ['draft', 'active', 'inactive'])->default('draft');

            $table->text('notes')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['quiz_template_id', 'version']);
            $table->index(['status', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_versions');
    }
};
