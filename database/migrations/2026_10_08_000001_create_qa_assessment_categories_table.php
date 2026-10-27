<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master kategori penilaian kualitatif (PRD QA Assessment §4/§9).
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian A
 *
 * Kategori seperti: Greeting, Handling Customer, Tes Call.
 * Diatur oleh QA (create/update/delete), dipakai saat menulis assessment.
 * Menggunakan slug untuk route key agar URL bersih.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qa_assessment_categories', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qa_assessment_categories');
    }
};