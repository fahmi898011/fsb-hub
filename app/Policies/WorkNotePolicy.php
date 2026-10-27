<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkNote;

/**
 * Otorisasi modul Catatan Kerja (PRD Catatan).
 *
 * AGENT: asisten | 2026-10-01 | buat berkas baru | modul Catatan Kerja, fase 1 backend
 *
 * Seluruh isi policy ini berbunyi satu hal: **pemilik saja.** Secara sengaja
 * TIDAK ada jalur istimewa untuk admin.
 *
 * Alasannya: catatan kerja adalah tulisan pribadi karyawan - bisa memuat draft,
 * keluhan, atau rencana yang belum tentu benar. Pada modul Chat pun sudah
 * diputuskan admin tidak boleh membaca DM (lihat ConversationPolicy). Catatan
 * pribadi tidak boleh lebih longgar daripada DM.
 *
 * Karena `view`/`update`/`delete` semuanya memakai pemeriksaan yang sama,
 * aturan ini juga diuji langsung di `WorkNoteAccessTest` supaya tidak
 * diam-diam melonggar bila kelak ada yang menambahkan cabang `admin`.
 */
class WorkNotePolicy
{
    /**
     * Daftar catatan sendiri - terbuka untuk siapa pun yang sudah login.
     * Tidak memakai permission: mencatat pekerjaan sendiri bukan wewenang
     * khusus, sama seperti membuat tiket IT.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /** Membuat catatan sendiri - terbuka untuk siapa pun yang sudah login. */
    public function create(User $user): bool
    {
        return true;
    }

    /** Membaca catatan: hanya pemiliknya. */
    public function view(User $user, WorkNote $note): bool
    {
        return $this->owns($user, $note);
    }

    /** Menyunting isi catatan: hanya pemiliknya. */
    public function update(User $user, WorkNote $note): bool
    {
        return $this->owns($user, $note);
    }

    /** Menghapus (artinya: memindahkan ke tong sampah): hanya pemiliknya. */
    public function delete(User $user, WorkNote $note): bool
    {
        return $this->owns($user, $note);
    }

    /** Memulihkan dari tong sampah: hanya pemiliknya. */
    public function restore(User $user, WorkNote $note): bool
    {
        return $this->owns($user, $note);
    }

    /**
     * Menyematkan / melepas sematan: hanya pemiliknya. Method terpisah supaya
     * niatnya terbaca, walau pemeriksaannya sama dengan `update`.
     */
    public function pin(User $user, WorkNote $note): bool
    {
        return $this->owns($user, $note);
    }

    /**
     * Hapus permanen sengaja TIDAK disediakan pada fase ini.
     *
     * Method ini selalu menolak dan ditulis eksplisit supaya jelas bahwa
     * penolakan itu disengaja - bukan pemeriksaan yang lupa dibuat. Tujuan
     * modulnya adalah catatan jangan hilang; menghapus permanen bertentangan
     * dengan itu, dan karyawan sudah cukup terlayani oleh tong sampah.
     */
    public function forceDelete(User $user, WorkNote $note): bool
    {
        return false;
    }

    /** Satu-satunya aturan pada modul ini. */
    private function owns(User $user, WorkNote $note): bool
    {
        return $note->user_id === $user->getKey();
    }
}
