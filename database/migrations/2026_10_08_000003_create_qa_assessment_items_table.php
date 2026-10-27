<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item penilaian per kategori (PRD QA Assessment §4/§9).
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian A
 *
 * Setiap assessment punya beberapa item (satu per kategori yang dipilih QA).
 * Kolom: strengths (kelebihan), weaknesses (kekurangan) — keduanya teks bebas.
 * Kategori bersifat opsional per assessment (QA pilih kategori mana yang dinilai).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qa_assessment_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('assessment_id')->constrained('qa_assessments')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('qa_assessment_categories')->cascadeOnDelete();

            $table->text('strengths')->nullable();   // kelebihan
            $table->text('weaknesses')->nullable();  // kekurangan

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['assessment_id', 'category_id'], 'qa_assessment_items_unique_per_category');
            $table->index(['assessment_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qa_assessment_items');
    }
};