<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Label catatan kerja (PRD Catatan §14 — pengelompokan).
 *
 * AGENT: asisten | 2026-10-06 | buat berkas baru | modul Catatan, lanjutan
 *
 * Dua tabel, bukan satu kolom JSON di `work_notes`. Alasan:
 * - Label dipakai untuk MENYARING daftar; menyaring berarti `WHERE`, dan
 *   `WHERE` atas kolom JSON tidak dapat memakai index dengan andal.
 * - Satu label sering dipakai banyak catatan; tabel label membuat penggantian
 *   nama label cukup satu baris, bukan menyunting tiap catatan.
 *
 * Label bersifat PER PENGGUNA (`user_id`): "Rapat" milik Budi tidak ada
 * hubungannya dengan "Rapat" milik Sari, dan tidak ada cara berbagi catatan
 * pada fase ini — jadi label pun tidak pernah lintas pengguna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);

            // Satu pengguna tidak boleh punya dua label dengan nama sama.
            // Dicegah di database, bukan hanya di kode, supaya penulisan
            // ganda (mis. dua tab menyimpan bersamaan) tidak menghasilkan
            // label kembar.
            $table->unique(['user_id', 'name']);

            $table->timestamps();
        });

        Schema::create('note_tag', function (Blueprint $table) {
            $table->foreignId('work_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('note_tag_id')->constrained()->cascadeOnDelete();

            // Satu catatan hanya sekali per label.
            $table->primary(['work_note_id', 'note_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_tag');
        Schema::dropIfExists('note_tags');
    }
};
