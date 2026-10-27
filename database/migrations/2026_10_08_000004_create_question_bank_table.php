<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bank soal ujian tertulis (PRD QA Assessment §5/§9).
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian B
 *
 * NAMA TABEL: `question_bank` (singular) — mengikuti PRD §9.
 * Berada di samping `question_banks` (plural) milik Quiz (Fase 4).
 * Dua modul berbeda, nama tabel sengaja tidak disamakan untuk menghindari bentrok.
 * Kolom `question_type`: essay | multiple_choice.
 * Opsi & kunci hanya untuk multiple_choice; essay dikosongkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_bank', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')->nullable()->constrained('qa_assessment_categories')->nullOnDelete();
            $table->string('question_type'); // essay | multiple_choice
            $table->text('question');
            $table->json('options')->nullable(); // [{"label":"A","text":"...","is_correct":true},...]
            $table->unsignedInteger('point')->default(1);
            $table->enum('status', ['active', 'inactive'])->default('active');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['category_id', 'status']);
            $table->index('question_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_bank');
    }
};