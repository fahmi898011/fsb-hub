<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master Scorecard (PRD Employee KPI/QA §5/§29).
 *
 * AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master
 *
 * Scorecard = pasangan (KPI, Channel). Contoh: KPI "Quality Score" divisi
 * Customer Service dengan channel "Inbound" melahirkan scorecard berbeda dari
 * channel "Chat", karena daftar parameternya berbeda. Bobot TIDAK disimpan di
 * sini, melainkan di Scorecard Version, supaya perubahan bobot tidak mengubah
 * assessment lama (PRD §6/§38).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scorecards', function (Blueprint $table) {
            $table->id();

            $table->foreignId('kpi_id')->constrained('kpis')->cascadeOnDelete();
            $table->foreignId('channel_id')->nullable()->constrained('channels')->nullOnDelete();

            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'active', 'inactive', 'archived'])->default('draft');
            $table->unsignedInteger('sort_order')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['kpi_id', 'channel_id']);
            $table->index(['status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scorecards');
    }
};
