<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Header penilaian kualitatif (PRD QA Assessment §4/§9).
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian A
 *
 * Satu baris = satu sesi penilaian seorang agent oleh satu QA pada satu periode.
 * Status: draft -> published. Bisa multi-QA per agent per periode (PRD §66).
 * Kolom `qa_id` mencatat QA yang menulis (PRD §67). `published_by` siapa yang klik Publish.
 * Catatan umum (`general_notes`) terpisah dari item per kategori (`qa_assessment_items`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qa_assessments', function (Blueprint $table) {
            $table->id();

            // Agent yang dinilai & QA yang menilai.
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('qa_id')->constrained('users')->cascadeOnDelete();

            // Periode QA (tabel terpisah qa_periods, bukan periode KPI).
            $table->foreignId('period_id')->constrained('qa_periods')->cascadeOnDelete();

            // Snapshot identitas untuk riwayat (agent/qa bisa berganti nama/divisi).
            $table->string('agent_name_snapshot');
            $table->string('qa_name_snapshot');
            $table->string('period_name_snapshot');

            // Catatan umum (di samping item per kategori).
            $table->text('general_notes')->nullable();

            // Alur: draft -> published.
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['agent_id', 'period_id']);
            $table->index(['qa_id', 'status']);
            $table->index(['period_id', 'status']);
            // Mencegah duplikat: satu QA hanya satu assessment per agent per periode.
            $table->unique(['qa_id', 'agent_id', 'period_id'], 'qa_assessments_unique_per_qa_agent_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qa_assessments');
    }
};