<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;

/**
 * Pemicu notifikasi dari modul IT Ticketing (PRD §42).
 *
 * Seperti InformationNotifier: dipisah dari NotificationService supaya
 * kalimat pesan, audiens, dan tautan terpusat. Notifikasi `type` selalu
 * `ticket` supaya ikon di daftar notifikasi konsisten (lihat
 * AppNotification::getIconAttribute()).
 *
 * Aturan audiens:
 * - Tiket baru            -> seluruh tim IT + Admin (pelaku dikecualikan).
 * - Balasan dari karyawan -> penanggung jawab; bila belum ada, antrean IT.
 * - Balasan dari tim IT   -> pemilik tiket (balasan internal dilewati).
 * - Tiket ditugaskan      -> penanggung jawab (bila bukan pelaku).
 * - Status berubah        -> pemilik tiket (bila bukan pelaku).
 */
class TicketNotifier
{
    /** Role yang menerima notifikasi antrean tiket. */
    private const QUEUE_ROLES = ['it', 'admin'];

    public function __construct(private readonly NotificationService $notifications) {}

    public function created(Ticket $ticket, ?User $actor = null): void
    {
        foreach (self::QUEUE_ROLES as $role) {
            $this->notifyRole(
                $role,
                'Tiket baru: ' . $ticket->ticket_number,
                ($actor?->name ?? 'Seorang karyawan') . ' membuat tiket "' . $ticket->title . '".',
                route('it.tickets.show', $ticket),
                $actor,
            );
        }
    }

    public function replied(Ticket $ticket, TicketReply $reply, ?User $actor = null): void
    {
        // Balasan internal adalah catatan antar anggota tim IT; pemilik tiket
        // tidak boleh diberi tahu keberadaannya.
        if ($reply->is_internal) {
            return;
        }

        // Pelaku adalah pemilik tiket -> penerima berikutnya adalah tim IT.
        if ($actor && $actor->id === $ticket->user_id) {
            if ($ticket->assigned_to && $ticket->assigned_to !== $actor->id) {
                $this->notify(
                    $ticket->assigned_to,
                    'Balasan baru: ' . $ticket->ticket_number,
                    $actor->name . ' membalas tiket yang Anda tangani.',
                    route('it.tickets.show', $ticket),
                );

                return;
            }

            foreach (self::QUEUE_ROLES as $role) {
                $this->notifyRole(
                    $role,
                    'Balasan baru: ' . $ticket->ticket_number,
                    $actor->name . ' membalas tiket "' . $ticket->title . '".',
                    route('it.tickets.show', $ticket),
                    $actor,
                );
            }

            return;
        }

        // Pelaku adalah tim IT -> beri tahu pemilik tiket.
        if ($ticket->user_id !== ($actor?->id)) {
            $this->notify(
                $ticket->user_id,
                'Balasan baru: ' . $ticket->ticket_number,
                'Tim IT membalas tiket Anda: "' . $ticket->title . '".',
                route('tickets.show', $ticket),
            );
        }
    }

    public function assigned(Ticket $ticket, ?User $actor = null): void
    {
        if (! $ticket->assigned_to || $ticket->assigned_to === $actor?->id) {
            return;
        }

        $this->notify(
            $ticket->assigned_to,
            'Tiket ditugaskan: ' . $ticket->ticket_number,
            'Anda ditugaskan menangani "' . $ticket->title . '".',
            route('it.tickets.show', $ticket),
        );
    }

    public function statusChanged(Ticket $ticket, ?User $actor = null): void
    {
        if ($ticket->user_id === $actor?->id) {
            return;
        }

        $this->notify(
            $ticket->user_id,
            'Status tiket diperbarui: ' . $ticket->ticket_number,
            'Status tiket "' . $ticket->title . '" sekarang: ' . $ticket->status_label . '.',
            route('tickets.show', $ticket),
        );
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                            */
    /* ------------------------------------------------------------------ */

    private function notify(User|int $user, string $title, ?string $message, string $url): void
    {
        $this->notifications->notify($user, $title, $message, 'ticket', $url);
    }

    /**
     * Kirim ke seluruh user AKTIF dengan role tertentu, kecuali pelakunya.
     * Akun nonaktif dilewati (PRD §6 — akun nonaktif tidak memakai aplikasi).
     */
    private function notifyRole(string $role, string $title, ?string $message, string $url, ?User $actor): void
    {
        User::role($role)
            ->when($actor, fn ($query, $user) => $query->whereKeyNot($user->getKey()))
            ->get()
            ->filter(fn (User $user) => $user->isActive())
            ->each(fn (User $user) => $this->notify($user, $title, $message, $url));
    }
}
