<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Form ujian tertulis (PRD QA Assessment §5/§9).
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian B
 *
 * QA buat form, pilih soal dari bank, atur opsi (retake, batas waktu, nilai lulus).
 * Status: draft -> published. Hanya published yang bisa di-assign ke agent.
 * `grading_method`: highest | latest | average (PRD: QA pilih nilai mana yg diambil saat retake).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_forms', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->text('description')->nullable();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            // Opsi form (semua opsional, PRD §99-§104).
            $table->boolean('allow_retake')->default(false);
            $table->unsignedInteger('max_attempts')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable(); // null = tanpa batas
            $table->decimal('passing_score', 5, 2)->nullable();    // ambang lulus (opsional)
            $table->enum('grading_method', ['highest', 'latest', 'average'])->default('highest');

            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamp('published_at')->nullable();

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['created_by', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_forms');
    }
};