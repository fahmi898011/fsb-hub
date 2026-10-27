<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penugasan QA ke agent (PRD QA Assessment §6/§9).
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian C
 *
 * Tabel terpisah (bukan `teams`). Alasan PRD §6: `teams` = pekerjaan (supervisor/PL),
 * ini = penilaian (siapa menilai siapa).
 * Satu QA handle banyak agent. Satu agent bisa di-handle beberapa QA (mis. QA A: handling, QA B: tes call).
 * Penetapan OLEH ADMIN (PRD §6) agar tidak ada QA mengklaim agent sendiri.
 * `active` untuk soft-toggle tanpa hapus riwayat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qa_agent_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('qa_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->cascadeOnDelete(); // Admin

            $table->boolean('active')->default(true);
            $table->text('notes')->nullable(); // catatan admin: cakupan penilaian, dll.

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['qa_id', 'agent_id'], 'qa_agent_assignments_unique_pair');
            $table->index(['qa_id', 'active']);
            $table->index(['agent_id', 'active']);
            $table->index('assigned_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qa_agent_assignments');
    }
};