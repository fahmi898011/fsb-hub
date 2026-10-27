<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jawaban per soal dalam sebuah pengerjaan (PRD Employee KPI/QA §33).
 *
 * AGENT: asisten | 2026-10-05 | buat berkas baru | modul Quiz, fase 4 quiz
 *
 * Satu baris = satu soal yang dijawab pada satu attempt. `selected_option_ids`
 * menyimpan daftar id opsi yang dipilih (JSON) karena soal `multiple` boleh
 * memilih lebih dari satu; untuk `single`/`true_false` isinya satu id.
 *
 * `is_correct` dan `point_snapshot` disimpan sebagai hasil final saat submit,
 * sehingga bila kunci soal berubah kemudian, riwayat penilaian tidak berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_answers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('quiz_attempt_id')->constrained('quiz_attempts')->cascadeOnDelete();
            $table->foreignId('quiz_version_question_id')->nullable()->constrained('quiz_version_questions')->nullOnDelete();

            $table->text('question_snapshot');
            $table->json('selected_option_ids')->nullable();

            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('point_snapshot')->default(1);

            $table->timestamps();

            $table->index('quiz_attempt_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_answers');
    }
};
