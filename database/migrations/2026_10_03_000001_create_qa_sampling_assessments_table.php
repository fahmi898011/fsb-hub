<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Header penilaian QA Sampling (PRD Employee KPI/QA §14/§15/§31).
 *
 * AGENT: asisten | 2026-10-03 | buat berkas baru | modul KPI, fase 2 QA sampling
 *
 * Satu baris = satu sample yang dinilai QA untuk seorang karyawan.
 *
 * Dua keputusan yang membentuk tabel ini:
 *
 * 1. NAMA TABEL memakai prefiks `qa_sampling_` meskipun PRD §31 menulis
 *    `qa_assessments`. Alasannya bukan selera: `qa_assessments` sudah dipakai
 *    oleh modul lain yang terpisah, "PRD QA Assessment" §9 (penilaian
 *    kualitatif berupa tulisan), dan permission-nya pun akan memakai
 *    `qa_assessment.*`. Memakai prefiks ini mencegah bentrok di level tabel
 *    DAN permission sekaligus, serta membuat satu modul terbaca utuh:
 *    `qa_sampling_*` + `qa_sampling.*` + rute `qa.sampling.*`.
 *
 * 2. SNAPSHOT, bukan pointer. Skor historis tidak boleh berubah bila master
 *    berubah (PRD §11/§38), maka nama scorecard, nomor versi, channel, dan
 *    nama karyawan disalin ke sini. Seluruh kunci asing memakai `nullOnDelete`
 *    supaya menghapus master tidak ikut menghapus riwayat penilaian.
 *
 * `final_score` dihitung ULANG di backend dari seluruh item (PRD §10), bukan
 * hasil pengurangan bertahap di frontend.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qa_sampling_assessments', function (Blueprint $table) {
            $table->id();

            // Karyawan yang dinilai (agent) dan QA yang menilai.
            $table->foreignId('employee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('qa_id')->nullable()->constrained('users')->nullOnDelete();

            // Divisi disimpan sebagai konteks saat penilaian: bila karyawan
            // pindah divisi, riwayat lama tetap memakai divisi lamanya (§46).
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();

            // Master yang dipakai. Versi harus aktif saat assessment dibuat.
            $table->foreignId('scorecard_id')->nullable()->constrained('scorecards')->nullOnDelete();
            $table->foreignId('scorecard_version_id')->nullable()->constrained('scorecard_versions')->nullOnDelete();
            $table->foreignId('channel_id')->nullable()->constrained('channels')->nullOnDelete();

            // Snapshot identitas (lihat docblock butir 2).
            $table->string('employee_name_snapshot')->nullable();
            $table->string('scorecard_name_snapshot');
            $table->unsignedInteger('version_number_snapshot')->nullable();
            $table->string('channel_name_snapshot')->nullable();

            // Sample yang dinilai. `sample_id` dipakai untuk mencegah sample
            // dinilai dua kali (PRD §48 butir 7); penegakannya di controller,
            // bukan unique index, supaya penilaian yang dibatalkan (cancelled)
            // tidak mengunci sample selamanya.
            $table->string('sample_link', 2048)->nullable();
            $table->string('sample_id')->nullable();

            $table->date('assessment_date');
            $table->string('period')->nullable();

            // Alur status: draft -> submitted -> reviewed -> locked, plus cancelled.
            $table->enum('status', ['draft', 'submitted', 'reviewed', 'locked', 'cancelled'])->default('draft');

            // Skor: mulai 100, dikurangi total penalty. ZTP `zero_score` yang
            // tersentuh memaksa `final_score` menjadi 0 (§13/§46).
            $table->decimal('initial_score', 5, 2)->default(100);
            $table->decimal('penalty_total', 6, 2)->default(0);
            $table->decimal('final_score', 5, 2)->nullable();
            $table->boolean('zero_tolerance_triggered')->default(false);

            $table->text('notes')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancelled_reason')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'assessment_date']);
            $table->index(['qa_id', 'status']);
            $table->index(['status', 'assessment_date']);
            $table->index('period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qa_sampling_assessments');
    }
};
