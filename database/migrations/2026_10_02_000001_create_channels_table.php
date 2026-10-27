<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master Channel (PRD Employee KPI/QA §4/§29).
 *
 * AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master
 *
 * Channel BUKAN KPI: channel adalah konteks yang menentukan scorecard dan
 * parameter mana yang dipakai (mis. Inbound vs Chat punya daftar parameter
 * berbeda). Karena itu channel berdiri sendiri sebagai master, bukan kolom
 * teks bebas di dalam scorecard.
 *
 * Divisi TIDAK dibuat ulang di sini. Semesta hanya boleh punya SATU master
 * divisi dan aplikasi ini sudah punya tabel `departments` (kelas `Department`)
 * yang secara eksplisit disebut "Divisi resmi user". Menambah tabel
 * `divisions` akan melahirkan dua sumber kebenaran untuk hal yang sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};
