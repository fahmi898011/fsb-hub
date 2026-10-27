<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

/**
 * Otorisasi aksi per-PESAN pada modul Chat (PRD Chat §15).
 *
 * AGENT: asisten | 2026-10-06 | buat berkas baru | modul Chat, lanjutan
 *
 * Dipisah dari ConversationPolicy karena pertanyaannya berbeda jenis.
 * ConversationPolicy menjawab "bolehkah saya berada di percakapan ini?"
 * (keanggotaan), sedangkan policy ini menjawab "bolehkah saya memperlakukan
 * PESAN INI begini?" — dan jawabannya bergantung pada siapa penulisnya,
 * bukan sekadar keanggotaan.
 *
 * Tiga aturan yang dijaga di sini:
 *
 * 1. Hanya PENULIS yang boleh menyunting. Riwayat percakapan dipakai sebagai
 *    rujukan kerja; pihak lain tidak boleh mengubah kalimat yang pernah
 *    dikirim seseorang.
 *
 * 2. Menghapus boleh oleh penulis, DAN oleh pengelola kanal (admin/qa) untuk
 *    pesan siapa pun di kanal itu — diperlukan untuk menindak pesan yang
 *    tidak pantas. Pada DM tidak ada pengelola, jadi hanya penulisnya.
 *
 * 3. Menyematkan adalah tindakan mengatur percakapan: pada kanal hanya
 *    pengelola, pada DM salah satu peserta cukup.
 *
 * Pemeriksaan keanggotaan dasar tetap dilakukan ConversationPolicy::view di
 * controller sebelum policy ini dipanggil; policy ini fokus pada kepemilikan.
 */
class MessagePolicy
{
    /**
     * Menyunting pesan: hanya penulis, dan hanya bila pesannya belum dihapus.
     *
     * Pesan terhapus tidak dapat disunting — teksnya sudah dimaksudkan hilang,
     * menyuntingnya hanya akan menghidupkan kembali baris itu.
     */
    public function update(User $user, Message $message): bool
    {
        return $message->isOwnedBy($user)
            && ! $message->trashed()
            && $this->withinEditWindow($message);
    }

    /**
     * Menghapus pesan (soft delete).
     *
     * Pengelola kanal dapat menghapus pesan siapa pun di kanalnya. Ini
     * disengaja: tanpa itu, satu pesan tidak pantas memaksa admin meminta
     * pengirimnya menghapus sendiri.
     */
    public function delete(User $user, Message $message): bool
    {
        if ($message->trashed()) {
            return false;
        }

        if ($message->isOwnedBy($user)) {
            return true;
        }

        $conversation = $message->conversation;

        return $conversation !== null
            && $conversation->isChannel()
            && ! $conversation->isArchived()
            && $user->can('channel.update');
    }

    /** Menyematkan / melepas sematan pesan. */
    public function pin(User $user, Message $message): bool
    {
        $conversation = $message->conversation;

        if ($conversation === null || $conversation->isArchived() || $message->trashed()) {
            return false;
        }

        if ($conversation->isChannel()) {
            return $user->can('channel.update');
        }

        return $conversation->hasMember($user);
    }

    /**
     * Batas waktu sunting (menit). `null`/0 berarti tanpa batas.
     *
     * Disediakan sebagai kebijakan yang dapat diatur, bukan konstanta di kode,
     * supaya bila kelak perlu dibatasi (mis. hanya 15 menit pertama) cukup
     * mengubah konfigurasi tanpa menyentuh policy.
     */
    private function withinEditWindow(Message $message): bool
    {
        $minutes = config('chat.edit_window_minutes');

        if (! $minutes || ! $message->created_at) {
            return true;
        }

        return $message->created_at->greaterThan(now()->subMinutes((int) $minutes));
    }
}
