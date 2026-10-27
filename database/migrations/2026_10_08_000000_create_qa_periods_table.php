<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Periode modul QA (PRD QA Assessment §7/§9).
 *
 * AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, periode
 *
 * Terpisah dari periode KPI (keputusan final PRD §7).
 * Digunakan oleh: qa_assessments, exam_assignments (via form/period relasi).
 * Bisa nested (parent_period_id) untuk hierarki mis. bulanan -> kuartal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qa_periods', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('slug')->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->foreignId('parent_period_id')->nullable()->constrained('qa_periods')->nullOnDelete();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('description')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'start_date', 'end_date']);
            $table->index('parent_period_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qa_periods');
    }
};