<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

/**
 * Otorisasi modul IT Ticketing (PRD §42 — Phase 5 IT).
 *
 * Dipisah dua ranah:
 * - Semua user terautentikasi boleh MEMBUAT tiket dan melihat tiket MILIKNYA
 *   (`tickets.*`). Tidak butuh permission khusus: bertanya ke IT adalah hak
 *   setiap karyawan.
 * - Hanya pemegang permission `ticket.view`/`ticket.update`/`ticket.assign`
 *   (role `it`, dan `admin` sebagai pengelola wadah) yang boleh masuk antrean
 *   bersama (`it.*`), membalas atas nama tim IT, menugaskan, dan mengubah
 *   status.
 *
 * Tiket bersifat rahasia antar karyawan: pemilik tiket tidak boleh membaca
 * tiket milik orang lain.
 */
class TicketPolicy
{
    /** Daftar tiket sendiri — terbuka untuk siapa pun yang sudah login. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /** Membuat tiket — terbuka untuk siapa pun yang sudah login. */
    public function create(User $user): bool
    {
        return true;
    }

    /** Pemilik tiket, atau pemegang izin `ticket.view` (tim IT & Admin). */
    public function view(User $user, Ticket $ticket): bool
    {
        return $ticket->user_id === $user->id
            || $user->can('ticket.view');
    }

    /** Membalas tiket — mengikuti aturan `view`. */
    public function reply(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket);
    }

    /** Memperbarui status / menutup tiket — hanya tim IT & Admin. */
    public function update(User $user, Ticket $ticket): bool
    {
        return $user->can('ticket.update');
    }

    /** Menugaskan penanggung jawab — hanya tim IT & Admin. */
    public function assign(User $user, Ticket $ticket): bool
    {
        return $user->can('ticket.assign');
    }

    /** Membuat catatan internal (tidak terlihat pemilik tiket). */
    public function writeInternalNote(User $user, Ticket $ticket): bool
    {
        return $user->can('ticket.update');
    }

    /** Tiket tidak pernah dihapus: jejak penanganan harus tetap tersimpan. */
    public function delete(User $user, Ticket $ticket): bool
    {
        return false;
    }
}
