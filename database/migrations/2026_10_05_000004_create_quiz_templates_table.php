<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Template quiz (PRD Employee KPI/QA §22/§33).
 *
 * AGENT: asisten | 2026-10-05 | buat berkas baru | modul Quiz, fase 4 quiz
 *
 * Template menjawab "quiz apa ini": judul, jumlah soal, passing score, batas
 * waktu, dan metode pemilihan soal. Aturan yang bisa berubah dari waktu ke
 * waktu (daftar soal, poin) TIDAK ada di sini, melainkan di `quiz_versions`.
 *
 * `selection_method` (PRD §24):
 *   manual   - QA memilih soal satu per satu.
 *   random   - sistem mengambil acak dari bank soal.
 *   category - sistem mengambil sejumlah soal per kategori.
 *
 * `time_limit` dalam menit; null berarti tanpa batas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_templates', function (Blueprint $table) {
            $table->id();

            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();

            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            $table->unsignedInteger('question_count')->default(10);
            $table->decimal('passing_score', 5, 2)->default(80);
            $table->unsignedInteger('time_limit')->nullable();

            $table->enum('selection_method', ['manual', 'random', 'category'])->default('manual');

            $table->enum('status', ['draft', 'active', 'inactive'])->default('draft');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_templates');
    }
};
