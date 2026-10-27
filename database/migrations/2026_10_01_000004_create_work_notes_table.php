<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan kerja pribadi (PRD Catatan) - satu tabel.
 *
 * AGENT: asisten | 2026-10-01 | buat berkas baru | modul Catatan Kerja, fase 1 backend
 *
 * Nama tabel sengaja `work_notes` dan BUKAN `notes`, karena di modul Tiket
 * sudah ada konsep "catatan internal" (`ticket_replies.is_internal`). Dua hal
 * itu berbeda dan rawan tertukar bila namanya sama.
 *
 * Keputusan yang menentukan bentuk tabel: **kepemilikan adalah satu-satunya
 * kunci akses**. Karena itu tabelnya datar - hanya `user_id` sebagai penjaga,
 * tanpa tabel pivot/pembagian. Membagikan catatan ke rekan ditunda, sehingga
 * tidak ada kolom akses yang harus diisi sekarang dan tidak ada risiko
 * catatan pribadi terbaca pihak lain.
 *
 * `lock_version` ada karena satu karyawan dapat membuka catatan yang sama di
 * dua PC sekaligus (justru itu masalah aslinya: sering berpindah PC). Tanpa
 * penanda versi, penyimpanan dari PC yang lebih tua akan menimpa yang lebih
 * baru tanpa peringatan. Client mengirim versi yang dipegangnya; bila tidak
 * cocok, server menolak (409) alih-alih menimpa.
 *
 * Soft delete dipakai (bukan hapus permanen) supaya catatan yang terhapus
 * masih dapat dipulihkan - sejalan dengan tujuan modul: catatan jangan hilang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_notes', function (Blueprint $table) {
            $table->id();

            // Pemilik catatan. Dihapus bersama akunnya (cascade), berbeda dari
            // audit log yang sengaja memakai nullOnDelete.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('title')->nullable();
            $table->longText('body')->nullable();

            // Disematkan di atas daftar. Opsional, untuk catatan yang sering dibuka.
            $table->boolean('pinned')->default(false);

            // Waktu sunting terakhir; dipakai untuk urutan tampil dan label
            // "terakhir disunting". Terpisah dari `updated_at` karena
            // penyematan/perubahan judul juga memperbarui `updated_at`.
            $table->timestamp('last_edited_at')->nullable();

            // Penanda versi untuk deteksi konflik antar-PC (lihat docblock).
            $table->unsignedInteger('lock_version')->default(1);

            $table->softDeletes();
            $table->timestamps();

            // Melayani daftar utama: catatan milik satu user, disematkan dulu.
            $table->index(['user_id', 'pinned', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_notes');
    }
};
