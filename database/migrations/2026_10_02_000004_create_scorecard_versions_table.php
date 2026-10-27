<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versi Scorecard (PRD Employee KPI/QA §6/§38).
 *
 * AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master
 *
 * Inilah titik versioning yang sesungguhnya. Aturannya: versi yang SUDAH
 * dipakai sebuah assessment tidak boleh diubah - bila bobot perlu berubah,
 * buat versi baru. Assessment menyimpan snapshot bobot, sehingga angka
 * September tetap 6% walau master Oktober menjadi 8%.
 *
 * Karena itu `version` (nomor) unik per scorecard, dan `effective_from` /
 * `effective_to` menandai masa berlakunya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scorecard_versions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('scorecard_id')->constrained('scorecards')->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->enum('status', ['draft', 'active', 'inactive', 'archived'])->default('draft');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['scorecard_id', 'version']);
            $table->index(['status', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scorecard_versions');
    }
};
