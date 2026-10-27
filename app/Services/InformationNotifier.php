<?php

namespace App\Services;

use App\Models\InformationItem;
use App\Models\InformationVersion;
use App\Models\User;

/**
 * Pemicu notifikasi dari modul Pusat Informasi.
 *
 * Dipisah dari NotificationService (yang murni transport: tulis ke tabel)
 * supaya kalimat pesan, audiens, dan tautan terpusat di satu tempat dan tidak
 * tersebar di controller.
 *
 * Catatan audiens:
 * - draft baru          -> sesama QA (belum terlihat karyawan)
 * - baru dipublikasikan -> seluruh karyawan
 * - versi dokumen baru  -> seluruh karyawan, hanya bila dokumen sudah published
 */
class InformationNotifier
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * Informasi baru dibuat (umumnya masih draft) -> beri tahu sesama QA.
     */
    public function created(InformationItem $item, ?User $actor = null): void
    {
        $this->notifyRole(
            'qa',
            'Draft informasi baru: ' . $item->title,
            ($actor?->name ?? 'Rekan QA') . ' membuat informasi baru yang masih berstatus draft.',
            route('qa.information.show', $item),
            $actor,
        );
    }

    /**
     * Informasi berubah menjadi published -> beri tahu seluruh karyawan.
     */
    public function published(InformationItem $item, ?User $actor = null): void
    {
        $category = $item->category?->name;

        $this->notifyRole(
            'employee',
            'Informasi baru: ' . $item->title,
            $category
                ? 'Informasi baru pada kategori ' . $category . '.'
                : 'Informasi baru telah dipublikasikan.',
            route('information.show', $item),
            $actor,
        );
    }

    /**
     * Versi dokumen baru diunggah -> beri tahu karyawan.
     *
     * Dilewati bila dokumen belum published, karena karyawan belum bisa
     * membukanya dan notifikasi akan menjadi tautan buntu.
     */
    public function versionAdded(InformationItem $item, InformationVersion $version, ?User $actor = null): void
    {
        if ($item->status !== 'published') {
            return;
        }

        $this->notifyRole(
            'employee',
            'Versi baru: ' . $item->title,
            'Dokumen diperbarui ke versi ' . $version->version . '.',
            route('information.show', $item),
            $actor,
        );
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Kirim ke seluruh user aktif dengan role tertentu, kecuali pelakunya.
     *
     * Pelaku dikecualikan agar QA tidak menerima notifikasi atas aksinya
     * sendiri. Akun nonaktif juga dilewati (PRD §6 — akun nonaktif tidak
     * lagi memakai aplikasi).
     */
    private function notifyRole(string $role, string $title, ?string $message, string $url, ?User $actor): void
    {
        User::role($role)
            ->when($actor, fn ($query, $user) => $query->whereKeyNot($user->getKey()))
            ->get()
            ->filter(fn (User $user) => $user->isActive())
            ->each(fn (User $user) => $this->notifications->notify(
                $user,
                $title,
                $message,
                'information',
                $url,
            ));
    }
}
