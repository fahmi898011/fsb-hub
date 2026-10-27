<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aturan target sampling mingguan (PRD Employee KPI/QA Sampling §17/§18).
 *
 * AGENT: asisten | 2026-10-04 | buat berkas baru | modul KPI, fase 3 QA reporting
 *
 * Tanpa tabel ini, laporan sampling tidak bisa menampilkan "Target/Completed/
 * Remaining/Completion %" — angkanya hanya relatif terhadap sesuatu. PRD §17
 * memberi contoh "5 sample / employee / week", sedangkan §18 merincinya
 * menjadi kolom: Division, Scorecard, Frequency, Target Type, Target Value,
 * Effective Date, Status.
 *
 * Kenapa satu tabel fleksibel, bukan beberapa kolom tetap: PRD §17 bagian TBD
 * menyatakan target masih bisa berarti per employee, per QA, per team, per
 * division, atau total mingguan. Kolom `target_type` menampung pilihan itu
 * tanpa perlu migrasi baru setiap kali kebijakan berubah.
 *
 * Baris lama tidak dihapus saat aturan berganti; `effective_date` + `status`
 * dipakai agar laporan periode lampau tetap dihitung dengan aturan yang
 * berlaku SAAT ITU (semangat PRD §11/§38: angka historis tidak berubah
 * hanya karena konfigurasi berubah).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qa_sampling_targets', function (Blueprint $table) {
            $table->id();

            // Konteks aturan. NULL = berlaku umum, bukan berarti "tanpa aturan".
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('scorecard_id')->nullable()->constrained('scorecards')->nullOnDelete();

            // Weekly adalah bentuk yang dipakai sekarang (§17 contohnya mingguan);
            // monthly disiapkan agar tidak perlu migrasi bila kebijakan berubah.
            $table->enum('frequency', ['weekly', 'monthly'])->default('weekly');

            // Sebagaimana PRD §17 TBD: target bisa dihitung per employee,
            // per QA, per team, per division, atau total.
            $table->enum('target_type', ['per_employee', 'per_qa', 'per_team', 'per_division', 'total'])
                ->default('per_employee');

            $table->unsignedInteger('target_value');
            $table->date('effective_date');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['status', 'effective_date']);
            $table->index(['department_id', 'frequency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qa_sampling_targets');
    }
};
