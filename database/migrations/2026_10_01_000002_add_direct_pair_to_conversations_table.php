<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda unik pasangan DM (rencana B4: race condition `findOrCreateDirect()`).
 *
 * Masalah: `ChatService::findOrCreateDirect()` mencari percakapan DM yang
 * sudah ada lalu membuat yang baru bila tidak ada. Dua permintaan paralel
 * (klik ganda pada "Mulai Chat", atau dua tab sekaligus) dapat lolos
 * pemeriksaan `existing` secara bersamaan dan membuat DUA percakapan DM untuk
 * pasangan orang yang sama.
 *
 * Mengapa tidak bisa di `conversation_members`: pasangan DM terdiri dari DUA
 * baris pivot (satu per anggota). Indeks unik yang melindungi pasangan harus
 * melihat kedua `user_id` dalam SATU baris, dan tidak ada kolom semacam itu
 * pada tabel pivot. Menambahkan kolom "partner" ke pivot adalah denormalisasi
 * yang tidak perlu, karena pasangan itu adalah sifat PERCAKAPAN, bukan
 * keanggotaan.
 *
 * Solusi: kolom `direct_pair` pada `conversations` yang berisi pasangan
 * kedua id anggota dalam urutan menaik ("12:34"). Urutan tetap inilah yang
 * membuat pasangan (A,B) dan (B,A) menghasilkan nilai yang SAMA — prinsip
 * yang sama dengan pemeriksaan `whereHas` ganda di service lama, hanya saja
 * sekarang dipegang oleh INDEKS UNIK sehingga tidak bisa dilanggar dua
 * transaksi sekaligus. Nilainya dihitung sekali oleh service; indeksnya
 * belum tentu dipakai untuk pencarian.
 *
 * Indeks unik `(type, direct_pair)` membatasi satu pasangan DM per pasangan
 * orang. SQLite (pengujian) maupun MySQL (produksi) sama-sama mendukungnya
 * karena kolomnya biasa, bukan ekspresi. `direct_pair` nullable: kanal
 * tidak memakainya, dan DM tanpa pasangan tidak ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            // "12:34" — dipisah ':' karena id numerik; panjang 42 cukup untuk
            // dua bigint (maks 19 digit) plus pemisah.
            $table->string('direct_pair', 42)->nullable()->after('type');

            // `type` diletakkan paling kiri: indeks ini juga mempercepat
            // pencarian DM (`scopeDirect`). Banyak baris `direct` dengan
            // `direct_pair` NULL (kanal) tidak melanggar keunikan — kedua
            // database memperlakukan NULL sebagai berbeda satu sama lain.
            $table->unique(['type', 'direct_pair'], 'conversations_direct_pair_unique');
        });

        // Percakapan DM yang sudah ada perlu diberi penanda, jika tidak
        // pengguna lama tidak bisa memulai DMnya kembali setelah migrasi.
        $this->backfillDirectPairs();
    }

    /**
     * Isi `direct_pair` dari anggota DM yang ada.
     *
     * DM lama yang ternyata ganda akibat race condition sebelumnya TIDAK
     * membuat migrasi gagal: yang diberi penanda hanya percakapan TERLAMA
     * per pasangan, sisanya dibiarkan tanpa penanda (muncul sebagai DM
     * ganda yang sudah ada — perbaikan ke depan, bukan urusan migrasi).
     */
    private function backfillDirectPairs(): void
    {
        $conversations = DB::table('conversations')
            ->where('type', 'direct')
            ->orderBy('id')
            ->get(['id']);

        foreach ($conversations as $conversation) {
            $memberIds = DB::table('conversation_members')
                ->where('conversation_id', $conversation->id)
                ->orderBy('user_id')
                ->pluck('user_id')
                ->all();

            if (count($memberIds) !== 2) {
                // Bukan DM utuh (mis. percakapan lama yang anggotanya
                // dihapus) — tidak diberi penanda.
                continue;
            }

            $pair = $memberIds[0] . ':' . $memberIds[1];

            $already = DB::table('conversations')
                ->where('type', 'direct')
                ->where('direct_pair', $pair)
                ->exists();

            if ($already) {
                continue;
            }

            DB::table('conversations')
                ->where('id', $conversation->id)
                ->update(['direct_pair' => $pair]);
        }
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropUnique('conversations_direct_pair_unique');
            $table->dropColumn('direct_pair');
        });
    }
};
