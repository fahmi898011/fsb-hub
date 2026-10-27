<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log pemakaian soal bank (PRD QA Assessment §5/§9).
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian B
 *
 * Mencatat: soal dipakai di form apa, oleh QA mana, dikirim ke agent mana.
 * Satu baris = satu pemakaian satu soal dalam satu assignment.
 * Menggunakan nullOnDelete agar riwayat tetap utuh walau master dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_usage_log', function (Blueprint $table) {
            $table->id();

            $table->foreignId('question_bank_id')->nullable()->constrained('question_bank')->nullOnDelete();
            $table->foreignId('exam_form_id')->nullable()->constrained('exam_forms')->nullOnDelete();
            $table->foreignId('exam_assignment_id')->nullable()->constrained('exam_assignments')->nullOnDelete();
            $table->foreignId('qa_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('used_at')->useCurrent();

            $table->index(['question_bank_id', 'used_at']);
            $table->index(['exam_form_id', 'exam_assignment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_usage_log');
    }
};