<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chat internal (PRD Chat §4) — empat tabel.
 *
 * Sengaja HANYA empat tabel: "kanal" dan "pesan langsung (DM)" bukan dua
 * konsep berbeda, melainkan dua tipe pada satu tabel `conversations`
 * (`direct` / `channel`). Dengan begitu daftar percakapan, badge belum
 * dibaca, dan percabangan otorisasi hanya ditulis satu kali.
 *
 * - `direct`  = percakapan dengan tepat dua anggota (DM 1-lawan-1).
 * - `channel` = percakapan grup; publik (default) atau privat.
 *
 * Nama kolom memakai bahasa Inggris mengikuti konvensi tabel lain
 * (`information_items`, `tickets`), sedangkan PRD menuliskan sebagian
 * kolom dalam bahasa Indonesia sebagai keterangan — lihat PRD Chat §4.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['direct', 'channel']);
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_private')->default(false);

            // Kanal yang "dihapus" diarsipkan, bukan dihapus permanen, supaya
            // riwayat pesan tetap utuh (PRD §6/§7).
            $table->timestamp('archived_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'archived_at']);
        });

        Schema::create('conversation_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Status "belum dibaca" bersifat per orang per percakapan, jadi
            // disimpan di sini — bukan di tabel `conversations` (PRD §4).
            $table->timestamp('last_read_at')->nullable();
            $table->timestamp('joined_at')->useCurrent();

            $table->timestamps();

            // Satu baris per pasangan: keanggotaan tidak boleh ganda.
            $table->unique(['conversation_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();

            // Nullable: akun yang dihapus tidak boleh menghapus percakapan
            // beserta seluruh riwayatnya.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('body');

            // Disiapkan sekarang untuk fitur balas/utas (fitur menyusul) agar
            // tidak perlu migration ulang (PRD §4).
            $table->foreignId('parent_id')->nullable()->constrained('messages')->nullOnDelete();

            // `edited_at` dan `deleted_at` dipakai fitur edit/hapus pesan yang
            // MASIH DITUNDA; kolomnya ada agar tidak ada migration susulan.
            $table->timestamp('edited_at')->nullable();
            $table->softDeletes();

            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });

        Schema::create('message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();

            // Disimpan di disk privat; `path` bukan URL publik (PRD §8).
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();

            $table->index('message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_attachments');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_members');
        Schema::dropIfExists('conversations');
    }
};
