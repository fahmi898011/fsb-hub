<?php

namespace App\Services;

use App\Exceptions\NoteConflictException;
use App\Models\NoteTag;
use App\Models\User;
use App\Models\WorkNote;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Logika modul Catatan Kerja (PRD Catatan).
 *
 * AGENT: asisten | 2026-10-01 | buat berkas baru | modul Catatan Kerja, fase 1 backend
 *
 * Dikumpulkan di satu service supaya aturan yang mudah salah tidak tersebar
 * di controller dan view:
 * - Setiap query daftar WAJIB lewat `OwnedBy` (privasi tidak bergantung pada UI).
 * - Batas jumlah catatan diperiksa di sini, bukan lewat validasi request,
 *   karena jumlahnya baru diketahui setelah membaca database.
 * - Aturan bentrok versi (dua PC) ada di satu tempat: `saveChanges()`.
 *
 * AGENT: asisten | 2026-10-06 | tambah ekspor (.txt/.md) | modul Catatan, lanjutan
 */
class WorkNoteService
{
    /** Format ekspor yang didukung (PRD Catatan §14). */
    public const EXPORT_TEXT = 'txt';

    public const EXPORT_MARKDOWN = 'md';

    /** Daftar format yang sah, dipakai validasi route/controller. */
    public const EXPORT_FORMATS = [self::EXPORT_TEXT, self::EXPORT_MARKDOWN];

    /** Daftar catatan milik user, dengan pencarian opsional. */
    public function listFor(User $user, ?string $search = null, ?int $tagId = null): LengthAwarePaginator
    {
        $search = trim((string) $search);

        return WorkNote::query()
            ->ownedBy($user)
            ->with('tags')
            ->when($search !== '', function ($query) use ($search) {
                // Pencarian hanya menyentuh catatan milik user sendiri, karena
                // scope di atas sudah lebih dulu menyaringnya.
                $query->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', '%' . $search . '%')
                        ->orWhere('body', 'like', '%' . $search . '%');
                });
            })
            ->when($tagId !== null, function ($query) use ($user, $tagId) {
                // Label yang dipakai untuk menyaring WAJIB milik user ini.
                // Tanpa pemeriksaan kepemilikan, id label orang lain yang
                // ditempelkan ke URL akan menyaring berdasarkan label itu —
                // dan menyiratkan keberadaannya.
                $query->whereHas('tags', function ($inner) use ($user, $tagId) {
                    $inner->whereKey($tagId)->where('user_id', $user->getKey());
                });
            })
            ->ordered()
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * Semua label milik user, beserta jumlah catatan aktifnya.
     *
     * Jumlah dipakai untuk menampilkan "(3)" di panel saring. Dihitung lewat
     * `withCount` pada relasi catatan agar label yang tidak dipakai tetap
     * muncul dengan angka 0 — label kosong perlu terlihat supaya dapat
     * dirapikan, bukan menghilang tanpa jejak.
     */
    public function tagsFor(User $user): Collection
    {
        return NoteTag::query()
            ->ownedBy($user)
            ->ordered()
            ->withCount(['notes' => fn ($query) => $query->whereNull('deleted_at')])
            ->get();
    }

    /** Catatan di tong sampah (untuk panel pemulihan). */
    public function trashedFor(User $user): Collection
    {
        return WorkNote::onlyTrashed()
            ->ownedBy($user)
            ->latest('deleted_at')
            ->get();
    }

    /**
     * Membuat catatan baru milik user.
     *
     * @param  array{title?: ?string, body?: ?string, pinned?: bool}  $data
     *
     * @throws ValidationException bila kuota catatan aktif sudah penuh
     */
    public function create(User $user, array $data): WorkNote
    {
        $limit = (int) config('notes.max_notes_per_user');

        if (WorkNote::query()->ownedBy($user)->count() >= $limit) {
            throw ValidationException::withMessages([
                'body' => "Jumlah catatan sudah mencapai batas ({$limit}). "
                    . 'Hapus catatan yang tidak dipakai lagi, atau pulihkan dari tong sampah.',
            ]);
        }

        return WorkNote::create([
            'user_id' => $user->getKey(),
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? '',
            'pinned' => (bool) ($data['pinned'] ?? false),
            // Catatan baru dihitung belum pernah disunting: `last_edited_at`
            // tetap null sampai isinya benar-benar diubah.
            'last_edited_at' => null,
            'lock_version' => 1,
        ]);
    }

    /**
     * Menyimpan perubahan isi catatan dengan pemeriksaan versi.
     *
     * @param  array{title?: ?string, body?: ?string}  $data
     * @param  int|null  $expectedVersion  versi yang dipegang client (null = lewati pemeriksaan)
     *
     * @throws NoteConflictException bila catatan sudah berubah di tempat lain
     */
    public function saveChanges(WorkNote $note, array $data, ?int $expectedVersion = null): WorkNote
    {
        // Dua PC memegang versi berbeda. Menimpa diam-diam berarti pekerjaan
        // di PC lain hilang - persis masalah yang ingin modul ini hilangkan.
        // Karena itu server yang memutuskan, bukan client.
        if ($expectedVersion !== null && $expectedVersion !== (int) $note->lock_version) {
            throw new NoteConflictException((int) $note->lock_version);
        }

        // Judul ikut menentukan `updated_at`, sehingga penanda "sudah disunting"
        // tetap naik walau isi belum berubah.
        $note->fill([
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? '',
        ]);

        $note->markEdited();
        $note->save();

        return $note->refresh();
    }

    /** Menyematkan / melepas sematan. Tidak dianggap sebagai suntingan isi. */
    public function togglePinned(WorkNote $note): WorkNote
    {
        $note->pinned = ! $note->pinned;
        $note->save();

        return $note->refresh();
    }

    /** Memindahkan catatan ke tong sampah (soft delete). */
    public function trash(WorkNote $note): void
    {
        $note->delete();
    }

    /** Memulihkan catatan dari tong sampah. */
    public function restore(WorkNote $note): WorkNote
    {
        $note->restore();

        return $note->refresh();
    }

    /* ------------------------------------------------------------------ */
    /* Label catatan (PRD Catatan §14)                                     */
    /* ------------------------------------------------------------------ */

    /**
     * Membuat label baru milik user, atau mengembalikan yang sudah ada.
     *
     * "Cari atau buat" dipilih supaya mengetik label yang sama dua kali tidak
     * gagal dengan galat unique — bagi pengguna, mengetik nama yang sudah ada
     * bukan kesalahan.
     */
    public function findOrCreateTag(User $user, string $name): NoteTag
    {
        $name = $this->normalizeTagName($name);

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'Nama label tidak boleh kosong.',
            ]);
        }

        $limit = (int) config('notes.max_tags_per_user');

        $existing = NoteTag::query()->ownedBy($user)->where('name', $name)->first();

        if ($existing !== null) {
            return $existing;
        }

        if (NoteTag::query()->ownedBy($user)->count() >= $limit) {
            throw ValidationException::withMessages([
                'name' => "Jumlah label sudah mencapai batas ({$limit}).",
            ]);
        }

        return NoteTag::create([
            'user_id' => $user->getKey(),
            'name' => $name,
        ]);
    }

    /** Menghapus label milik user; tautan ke catatan ikut lepas (cascade). */
    public function deleteTag(NoteTag $tag): void
    {
        $tag->delete();
    }

    /**
     * Mengganti seluruh label sebuah catatan.
     *
     * Dikirim sebagai daftar UTUH (bukan tambah/hapus satu per satu) karena
     * panel label di UI memang bekerja begitu: pengguna mencentang, lalu
     * menyimpan. Mengirim daftar utuh membuat hasil akhir tidak bergantung
     * pada urutan permintaan.
     *
     * @param  array<int, int|string>  $tagIds
     */
    public function syncTags(WorkNote $note, User $user, array $tagIds): WorkNote
    {
        // Hanya label milik user ini yang boleh tertaut. Id asing diabaikan
        // diam-diam, bukan ditolak: pemilik catatan tidak perlu tahu label
        // siapa yang kebetulan dikirim.
        $valid = NoteTag::query()
            ->ownedBy($user)
            ->whereKey($tagIds)
            ->pluck('id')
            ->all();

        $note->tags()->sync($valid);

        return $note->refresh()->load('tags');
    }

    /** Nama label dinetralkan: spasi ganda dirapikan, dibatasi panjangnya. */
    private function normalizeTagName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');

        return mb_substr($name, 0, (int) config('notes.tag_max_length'));
    }

    /* ------------------------------------------------------------------ */
    /* Ekspor (PRD Catatan §14)                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Menyiapkan ekspor SATU catatan.
     *
     * Mengembalikan array `[nama_berkas, isi]`, bukan objek Response: service
     * ini sengaja tetap bebas-HTTP supaya bentuk ekspor dapat diuji tanpa
     * mengirim permintaan, dan supaya controller bebas memilih cara
     * mengirimkannya (unduhan langsung).
     *
     * @return array{0: string, 1: string}
     */
    public function exportNote(WorkNote $note, string $format = self::EXPORT_TEXT): array
    {
        $format = $this->normalizeExportFormat($format);

        $content = $format === self::EXPORT_MARKDOWN
            ? $this->noteAsMarkdown($note)
            : $this->noteAsText($note);

        return [$this->exportFilename($note->display_title, $format), $content];
    }

    /**
     * Menyiapkan ekspor SELURUH catatan aktif milik user.
     *
     * Hanya catatan AKTIF (bukan yang di tong sampah): ekspor dimaksudkan
     * untuk membawa pekerjaan keluar, bukan mengabadikan sampah. Urutannya
     * sama dengan daftar di halaman (disematkan lebih dulu) supaya hasilnya
     * terasa familier.
     *
     * @return array{0: string, 1: string}
     */
    public function exportAll(User $user, string $format = self::EXPORT_TEXT): array
    {
        $format = $this->normalizeExportFormat($format);

        $notes = WorkNote::query()->ownedBy($user)->ordered()->get();

        if ($format === self::EXPORT_MARKDOWN) {
            $parts = $notes->map(fn (WorkNote $note) => $this->noteAsMarkdown($note, false))->all();
            $content = "# Catatan Kerja\n\n" . implode("\n\n---\n\n", $parts);
        } else {
            $parts = $notes->map(fn (WorkNote $note) => $this->noteAsText($note, false))->all();
            $content = "CATATAN KERJA\n=============\n\n" . implode("\n\n" . str_repeat('-', 40) . "\n\n", $parts);
        }

        $stamp = now()->format('Ymd-His');

        return ["catatan-kerja-{$stamp}.{$format}", $content];
    }

    /** Bentuk teks biasa: judul, waktu, lalu isi apa adanya. */
    private function noteAsText(WorkNote $note, bool $withTitle = true): string
    {
        $lines = [];

        if ($withTitle) {
            $lines[] = $note->display_title;
            $lines[] = str_repeat('=', mb_strlen($note->display_title));
        }

        $lines[] = 'Disunting: ' . ($note->last_edited_at?->format('d/m/Y H:i') ?? 'belum pernah diisi');
        $lines[] = '';
        $lines[] = (string) $note->body;

        return implode("\n", $lines);
    }

    /**
     * Bentuk Markdown.
     *
     * Isi catatan ditaruh APA ADANYA, bukan dikutip: karyawan menulis catatan
     * dengan Markdown sendiri, dan mengutipnya akan merusak penandaannya.
     * Judul dinetralkan dari `#` di awal agar tidak berubah jadi heading.
     */
    private function noteAsMarkdown(WorkNote $note, bool $withTitle = true): string
    {
        $lines = [];

        if ($withTitle) {
            $lines[] = '# ' . $this->escapeMarkdownHeading($note->display_title);
            $lines[] = '';
        }

        $lines[] = '> Disunting: ' . ($note->last_edited_at?->format('d/m/Y H:i') ?? 'belum pernah diisi');
        $lines[] = '';
        $lines[] = (string) $note->body;

        return implode("\n", $lines);
    }

    /** Judul aman untuk heading Markdown (buang `#` di awal). */
    private function escapeMarkdownHeading(string $title): string
    {
        return ltrim($title, "# \t");
    }

    /** Nama berkas aman dari judul catatan. */
    private function exportFilename(string $title, string $format): string
    {
        $slug = Str::slug(Str::limit($title, 60, ''));

        if ($slug === '') {
            $slug = 'catatan';
        }

        return "{$slug}.{$format}";
    }

    /** Terima hanya format yang dikenal; sisanya jatuh ke teks biasa. */
    private function normalizeExportFormat(string $format): string
    {
        $format = strtolower(trim($format));

        return $format === self::EXPORT_MARKDOWN ? self::EXPORT_MARKDOWN : self::EXPORT_TEXT;
    }
}
