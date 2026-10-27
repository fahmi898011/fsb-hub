<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pin pesan pada percakapan (PRD Chat §15 — fitur yang ditunda, kini diaktifkan).
 *
 * AGENT: asisten | 2026-10-06 | buat berkas baru | modul Chat, lanjutan
 *
 * Satu kolom, bukan tabel baru: pin adalah keadaan SATU pesan, dan sebuah
 * pesan hanya berada di satu percakapan. Menyimpannya di `messages` membuat
 * "pesan yang disematkan" selalu konsisten dengan pesannya sendiri — tidak ada
 * baris pin yang bisa menunjuk pesan yang sudah dihapus tanpa terdeteksi.
 *
 * `pinned_at` (bukan boolean `is_pinned`) menyimpan JUGA kapan pin dipasang,
 * sehingga daftar pin dapat diurutkan menurut waktu semat tanpa kolom tambahan,
 * dan "unpin" cukup mengosongkan nilainya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->timestamp('pinned_at')->nullable()->after('edited_at');

            // Daftar pin per percakapan selalu diambil dengan pola
            // `where conversation_id = ? and pinned_at is not null`,
            // jadi index komposit ini yang dipakai.
            $table->index(['conversation_id', 'pinned_at']);
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['conversation_id', 'pinned_at']);
            $table->dropColumn('pinned_at');
        });
    }
};
