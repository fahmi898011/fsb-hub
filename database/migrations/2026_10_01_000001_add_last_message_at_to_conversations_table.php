<?php

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom `last_message_at` pada `conversations` (rencana B2).
 *
 * Sebelumnya daftar percakapan diurutkan dengan subquery yang membaca
 * `max(messages.created_at)` PER BARIS percakapan — query berkorelasi yang
 * tidak dapat memakai index dengan baik, karena setiap baris mengeksekusi
 * satu query terhadap seluruh tabel `messages`.
 *
 * Gantinya: waktu pesan terakhir disimpan sebagai kolom biasa dan dijaga oleh
 * `ChatService::send()`. Pengurutan tinggal membaca index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            // Diletakkan setelah `archived_at` agar berdekatan dengan kolom
            // waktu lainnya. Nullable: percakapan tanpa pesan (mis. kanal
            // yang baru saja dibuat) belum punya waktu pesan terakhir.
            $table->timestamp('last_message_at')->nullable()->after('archived_at');

            // Index komposit mengikuti cara pengurutan daftar:
            // `last_message_at` DESC, lalu `created_at` DESC sebagai cadangan
            // untuk percakapan yang belum punya pesan. Kolom `last_message_at`
            // terlebih dahulu (paling kiri) supaya index terpakai penuh.
            $table->index(['last_message_at', 'created_at'], 'conversations_last_message_at_index');
        });

        // Percakapan yang sudah ada punya riwayat: isikan dari pesan terakhirnya
        // supaya urutan daftar tidak berubah setelah migrasi ini.
        //
        // Satu statement UPDATE dengan subquery berkorelasi — SQL standar yang
        // berjalan sama di MySQL (produksi) dan SQLite (pengujian). Pesan yang
        // sudah dihapus-lunak TIDAK dihitung: riwayat yang ditampilkan juga
        // memfilternya, jadi `last_message_at` harus konsisten dengan itu.
        DB::statement(
            'UPDATE conversations SET last_message_at = ('
            . 'SELECT MAX(messages.created_at) FROM messages '
            . 'WHERE messages.conversation_id = conversations.id '
            . 'AND messages.deleted_at IS NULL)'
            . ' WHERE EXISTS ('
            . 'SELECT 1 FROM messages '
            . 'WHERE messages.conversation_id = conversations.id '
            . 'AND messages.deleted_at IS NULL)'
        );
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropIndex('conversations_last_message_at_index');
            $table->dropColumn('last_message_at');
        });
    }
};
