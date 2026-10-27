<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master kategori soal (PRD Employee KPI/QA §21 "Question Bank").
 *
 * AGENT: asisten | 2026-10-05 | buat berkas baru | modul Quiz, fase 4 quiz
 *
 * Kategori soal memisahkan bank soal per topik (mis. SOP, Juknis, Script, FAQ).
 * Ia dipisahkan dari `departments` karena satu divisi dapat memiliki banyak
 * topik soal, dan satu topik dapat dipakai lintas divisi bila perlu.
 *
 * Dihapusnya kategori TIDAK menghapus soal: kolom `question_banks.category_id`
 * memakai `nullOnDelete`, sebab bank soal adalah aset pengetahuan yang tidak
 * boleh hilang hanya karena penataan kategori berubah (PRD §11: histori utuh).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_categories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();

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
        Schema::dropIfExists('question_categories');
    }
};
