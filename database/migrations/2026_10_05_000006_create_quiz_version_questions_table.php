<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soal yang dipakai pada sebuah versi quiz (PRD Employee KPI/QA §23/§33).
 *
 * AGENT: asisten | 2026-10-05 | buat berkas baru | modul Quiz, fase 4 quiz
 *
 * Tabel inilah yang membuat versi quiz "membekukan" soal. Ia menyimpan SALINAN
 * identitas soal yang benar-benar dipakai (teks, tipe) dan POIN saat itu
 * (`point_snapshot`). Dengan begitu, mengubah soal di bank atau mengubah poin
 * defaultnya tidak mengubah quiz yang sudah terbit (PRD §11/§23).
 *
 * `question_bank_id` boleh null (nullOnDelete): bila soal di bank dihapus,
 * riwayat quiz tetap utuh karena salinannya ada di sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_version_questions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('quiz_version_id')->constrained('quiz_versions')->cascadeOnDelete();
            $table->foreignId('question_bank_id')->nullable()->constrained('question_banks')->nullOnDelete();

            // Snapshot identitas soal + poin (lihat docblock).
            $table->text('question_snapshot');
            $table->string('question_type_snapshot', 20)->default('single');
            $table->unsignedInteger('point_snapshot')->default(1);

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['quiz_version_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_version_questions');
    }
};
