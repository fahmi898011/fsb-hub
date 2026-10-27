<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pilihan jawaban sebuah soal (PRD Employee KPI/QA §32 "question_options").
 *
 * AGENT: asisten | 2026-10-05 | buat berkas baru | modul Quiz, fase 4 quiz
 *
 * Untuk soal `true_false` tetap dipakai baris opsi (Benar/Salah) supaya mesin
 * penilaian hanya punya SATU bentuk data — soal esai tidak ada di Quiz (itu
 * milik modul QA Assessment), sehingga setiap soal selalu punya opsi.
 *
 * `is_correct` menandai kunci jawaban. Soal `multiple` boleh punya lebih dari
 * satu baris bertanda benar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_options', function (Blueprint $table) {
            $table->id();

            $table->foreignId('question_bank_id')->constrained('question_banks')->cascadeOnDelete();

            $table->string('option_label', 8)->nullable();
            $table->text('option_text');
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['question_bank_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_options');
    }
};
