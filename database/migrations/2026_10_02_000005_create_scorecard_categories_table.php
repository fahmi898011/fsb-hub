<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kategori Scorecard (PRD Employee KPI/QA §7/§30).
 *
 * AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master
 *
 * Kategori hanya MENGELOMPOKKAN parameter (mis. "Opening & Closing",
 * "Resolution / Accuracy") agar form QA mudah dibaca. Kategori melekat pada
 * `scorecard_version_id` - bukan pada scorecard - supaya susunan kategori ikut
 * ter-versioning bersama bobotnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scorecard_categories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('scorecard_version_id')->constrained('scorecard_versions')->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['scorecard_version_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scorecard_categories');
    }
};
