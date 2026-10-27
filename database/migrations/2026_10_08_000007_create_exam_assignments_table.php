<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distribusi form ujian ke agent (PRD QA Assessment §5/§9).
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian B
 *
 * Satu baris = satu agent dikerjakan satu form (satu attempt_ke).
 * Status: assigned -> in_progress -> submitted -> graded -> published.
 * QA menilai (graded_by/graded_at), lalu Publish -> agent lihat nilai.
 * `score_picked` = nilai yg diambil bila retake (highest/latest/average).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('form_id')->constrained('exam_forms')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->cascadeOnDelete(); // QA yang assign

            // Attempt ke-N (untuk retake).
            $table->unsignedInteger('attempt_ke')->default(1);

            // Status alur (PRD §107-§108).
            $table->enum('status', ['assigned', 'in_progress', 'submitted', 'graded', 'published'])->default('assigned');

            // Waktu pengerjaan.
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Penilaian.
            $table->decimal('total_score', 6, 2)->nullable(); // total skor earned
            $table->decimal('max_score', 6, 2)->nullable();   // total poin maksimum
            $table->decimal('percentage', 5, 2)->nullable();  // persentase
            $table->enum('result', ['pass', 'fail'])->nullable(); // lulus/tidak (berdasarkan passing_score)

            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();

            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();

            // Nilai yg diambil bila retake (highest/latest/average).
            $table->enum('score_picked', ['highest', 'latest', 'average'])->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['agent_id', 'status']);
            $table->index(['form_id', 'status']);
            $table->index(['assigned_by', 'status']);
            $table->unique(['form_id', 'agent_id', 'attempt_ke'], 'exam_assignments_unique_attempt');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_assignments');
    }
};