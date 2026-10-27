<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengerjaan quiz (PRD Employee KPI/QA §27/§33).
 *
 * AGENT: asisten | 2026-10-05 | buat berkas baru | modul Quiz, fase 4 quiz
 *
 * Satu baris = satu kali karyawan mengerjakan satu versi quiz. Seluruh angka
 * hasil (jumlah benar/salah, total poin, skor, PASS/FAIL) DISIMPAN di sini
 * sebagai hasil FINAL saat submit, bukan dihitung ulang setiap ditampilkan.
 * Alasannya: hasil quiz menjadi dasar KPI, jadi angkanya tidak boleh berubah
 * bila soal/master berubah setelahnya (PRD §11/§38).
 *
 * `quiz_version_id` memakai `restrictOnDelete`? Tidak — `nullOnDelete`, agar
 * menghapus versi tidak menghapus bukti pengerjaan. Identitas versi & template
 * tetap terbaca dari kolom snapshot di bawah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('quiz_version_id')->nullable()->constrained('quiz_versions')->nullOnDelete();
            $table->foreignId('quiz_template_id')->nullable()->constrained('quiz_templates')->nullOnDelete();

            // Snapshot identitas quiz (lihat docblock).
            $table->string('quiz_name_snapshot');
            $table->unsignedInteger('version_number_snapshot')->nullable();
            $table->decimal('passing_score_snapshot', 5, 2)->default(80);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->unsignedInteger('total_questions')->default(0);
            $table->unsignedInteger('correct_count')->default(0);
            $table->unsignedInteger('wrong_count')->default(0);
            $table->decimal('total_point', 8, 2)->default(0);
            $table->decimal('earned_point', 8, 2)->default(0);
            $table->decimal('score', 5, 2)->nullable();
            $table->enum('result', ['pending', 'pass', 'fail'])->default('pending');

            $table->string('period')->nullable();

            $table->timestamps();

            $table->index(['employee_id', 'created_at']);
            $table->index(['quiz_version_id', 'result']);
            $table->index('period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
    }
};
