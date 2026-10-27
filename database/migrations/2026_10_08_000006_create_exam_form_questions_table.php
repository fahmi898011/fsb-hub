<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soal yang dipakai pada form ujian (PRD QA Assessment §9).
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian B
 *
 * Pivot form <-> question_bank dengan kolom tambahan: urutan & poin override.
 * Poin di sini boleh beda dari point di question_bank (QA atur per form).
 * Snapshot tipe soal & opsi disalin ke sini saat ditambah (mirip QuizVersionQuestion).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_form_questions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('form_id')->constrained('exam_forms')->cascadeOnDelete();
            $table->foreignId('question_bank_id')->constrained('question_bank')->cascadeOnDelete();

            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('point')->default(1); // override poin untuk form ini

            // Snapshot saat ditambahkan ke form (versi mandiri, PRD §23 analog).
            $table->string('question_type_snapshot');
            $table->text('question_snapshot');
            $table->json('options_snapshot')->nullable();
            $table->unsignedInteger('point_snapshot')->default(1);

            $table->timestamps();

            $table->unique(['form_id', 'question_bank_id'], 'exam_form_questions_unique_per_form');
            $table->index(['form_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_form_questions');
    }
};