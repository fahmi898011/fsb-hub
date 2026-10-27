<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nama kanal unik (rencana B6).
 *
 * Sebelumnya dua kanal boleh bernama persis sama. PRD tidak melarang, tetapi
 * di daftar percakapan dua nama yang sama sangat membingungkan: pengguna
 * tidak tahu mana yang akan dibukanya.
 *
 * Indeks unik pada `name` adalah LAPIS KEDUA. Lapis pertamanya adalah
 * validasi `Rule::unique` di `ConversationController::store()`, yang sudah
 * memeriksa case-insensitive sejak sekarang. Indeks ini menjaga kasus yang
 * tidak bisa ditangkap validasi: dua request POST pembuatan kanal paralel.
 *
 * Catatan tentang case-insensitivity:
 * - Produksi (MySQL) memakai collation default `utf8mb4_unicode_ci` yang
 *   SUDAH case-insensitive, jadi 'Tim QA' dan 'TIM QA' dianggap sama oleh
 *   indeks ini tanpa perubahan skema.
 * - Pengujian (SQLite) membandingkan TEXT case-sensitive, jadi di lingkungan
 *   tes indeks ini tidak menjaga perbedaan huruf besar/kecil. Yang diuji di
 *   SQLite adalah validasi controller; di MySQL, indeks ini menjadi penjaga
 *   terakhir untuk request paralel (pola yang sama dengan B4).
 * Karena itu TIDAK ada perubahan collation di migrasi ini: `MODIFY COLUMN`
 * tidak didukung SQLite, dan MySQL sudah case-insensitive sejak awal.
 *
 * DM membiarkan `name` NULL, dan NULL tidak melanggar keunikan di kedua
 * database, sehingga indeks ini tidak menghalalkan DM.
 *
 * `archived_at` sengaja TIDAK disertakan dalam indeks: kanal yang diarsipkan
 * tetap memakai namanya, dan itu tidak boleh "membebaskan" nama untuk kanal
 * baru — kanal lama masih ada di database dan masih bisa dibuka kembali
 * langsung lewat URL. Konsisten dengan pesan validasi controller.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->unique('name', 'conversations_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropUnique('conversations_name_unique');
        });
    }
};
