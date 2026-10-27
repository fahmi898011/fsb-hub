<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

/**
 * Otorisasi modul Chat (PRD Chat §7/§10).
 *
 * Prinsip yang menentukan bentuk policy ini: **keanggotaan adalah satu-satunya
 * kunci.** Percakapan yang tidak diikuti tidak dapat dibaca siapa pun,
 * termasuk `admin`.
 *
 * Konsekuensinya privasi DM tidak perlu aturan khusus "admin dilarang membaca":
 * admin memang bukan peserta DM mana pun, jadi `view` otomatis menolak.
 * Larangan itu diuji langsung pada test supaya tidak diam-diam hilang bila
 * kelak keanggotaan berubah.
 *
 * Pembuatan & pengelolaan kanal tetap butuh permission eksplisit
 * (`channel.*`) sehingga dapat dibatasi lebih lanjut tanpa mengubah policy.
 *
 * AGENT: asisten | 2026-10-06 | method per-pesan (update/delete/pin) dipindah ke MessagePolicy | modul Chat §15
 */
class ConversationPolicy
{
    /** Semua karyawan terautentikasi boleh membuka halaman chat. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Membaca sebuah percakapan: hanya anggotanya.
     *
     * Kanal yang diarsipkan tidak dapat dibuka lagi lewat URL langsung;
     * arsip berarti "kanal ditutup", riwayatnya tetap tersimpan di database.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        return ! $conversation->isArchived()
            && $conversation->hasMember($user);
    }

    /** Membuat kanal — hanya admin dan qa (PRD §6). */
    public function create(User $user): bool
    {
        return $user->can('channel.create');
    }

    /** Menambah/menghapus anggota kanal — hanya admin dan qa (PRD §6). */
    public function manageMembers(User $user, Conversation $conversation): bool
    {
        return $conversation->isChannel()
            && $user->can('channel.manage_members');
    }

    public function update(User $user, Conversation $conversation): bool
    {
        return $conversation->isChannel()
            && $user->can('channel.update');
    }

    /** "Menghapus" kanal berarti mengarsipkannya — hanya admin (PRD §6). */
    public function archive(User $user, Conversation $conversation): bool
    {
        return $conversation->isChannel()
            && ! $conversation->isArchived()
            && $user->can('channel.delete');
    }

    /**
     * Mengirim pesan.
     *
     * Dua syarat: pengirim aktif dan merupakan anggota. Khusus DM, lawan
     * bicara juga harus aktif — akun nonaktif tetap tampil di riwayat, tetapi
     * tidak dapat dikirimi pesan baru (PRD §7).
     */
    public function sendMessage(User $user, Conversation $conversation): bool
    {
        if (! $user->isActive() || ! $conversation->hasMember($user)) {
            return false;
        }

        if ($conversation->isDirect()) {
            return $conversation->otherParticipant($user)?->isActive() ?? false;
        }

        return true;
    }

    /** Memulai DM dengan karyawan lain: bukan diri sendiri, keduanya aktif. */
    public function startDirect(User $user, User $target): bool
    {
        return $user->isActive()
            && $target->isActive()
            && ! $user->is($target);
    }
}
