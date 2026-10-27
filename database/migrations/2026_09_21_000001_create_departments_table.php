<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Divisi (departments) — PRD KPI §11.
 *
 * Sebelumnya `users.department` berupa teks bebas, sehingga "IT", "it", dan
 * "I.T." dianggap tiga divisi berbeda. Tabel ini menjadikannya master data.
 *
 * Catatan: Divisi BUKAN hak akses (itu tugas Role). Kolom `users.department`
 * (teks) sengaja DIPERTAHANKAN untuk kompatibilitas, dan `department_id`
 * ditambahkan sebagai acuan resmi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('code')->nullable()->unique();
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['status', 'sort_order']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')
                ->nullable()
                ->after('department')
                ->constrained('departments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });

        Schema::dropIfExists('departments');
    }
};
