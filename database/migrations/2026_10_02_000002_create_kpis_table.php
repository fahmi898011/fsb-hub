<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master KPI (PRD Employee KPI/QA §4/§29).
 *
 * AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master
 *
 * Satu KPI adalah "apa yang diukur" untuk sebuah divisi (mis. "Quality
 * Score"), sedangkan "bagaimana mengukurnya" ada di Scorecard. KPI dibuat
 * datar (tanpa tabel versi terpisah seperti `kpi_versions` di PRD §30):
 * VERSIONING yang benar-benar berubah dari waktu ke waktu adalah bobot
 * parameter, dan bobot itu melekat pada Scorecard Version - bukan pada KPI.
 * Menambah versi di dua tempat hanya menggandakan beban pemeliharaan tanpa
 * memberi ketahanan histori tambahan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpis', function (Blueprint $table) {
            $table->id();

            // Divisi pemilik KPI. Memakai master `departments` yang sudah ada.
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('code', 20)->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['department_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpis');
    }
};
