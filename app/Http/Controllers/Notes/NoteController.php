<?php

namespace App\Http\Controllers\Notes;

use App\Http\Controllers\Controller;
use App\Models\NoteTag;
use App\Models\WorkNote;
use App\Services\WorkNoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Catatan kerja pribadi (PRD Catatan) - fase 1 backend.
 *
 * AGENT: asisten | 2026-10-01 | buat berkas baru | modul Catatan Kerja, fase 1 backend
 *
 * Tanpa permission: ini alat pribadi, seperti tiket IT. Yang menjaga bukan
 * permission melainkan kepemilikan, dan itu diputuskan WorkNotePolicy.
 *
 * Setiap aksi memanggil `authorize()` lebih dulu - termasuk saat memuat catatan
 * lewat route model binding, supaya catatan milik orang lain tidak pernah
 * sempat dibaca dari database apalagi dikirim ke halaman.
 *
 * Endpoint tulis mengembalikan JSON bila diminta lewat fetch (autosave),
 * dan redirect biasa bila dipakai lewat form HTML tanpa JavaScript. Satu
 * endpoint melayani dua cara supaya perilakunya tidak bercabang jadi dua kode.
 *
 * AGENT: asisten | 2026-10-06 | tambah ekspor (.txt/.md) | modul Catatan, lanjutan
 */
class NoteController extends Controller
{
    public function __construct(private readonly WorkNoteService $notes) {}

    /** Daftar catatan + panel editor (satu halaman dua panel). */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', WorkNote::class);

        $user = $request->user();
        $search = $request->query('q');
        // Saring menurut label. Id asing (bukan milik user) diperlakukan
        // sebagai "tidak menyaring" oleh service, bukan membocorkan bahwa
        // label itu ada.
        $tagId = (int) $request->query('tag') ?: null;

        // Catatan yang sedang dibuka. Sengaja dicari lewat scope `ownedBy`,
        // bukan `find()` biasa: id catatan orang lain yang ditempelkan ke URL
        // tidak boleh membuka apa pun - hasilnya "tidak ada yang dipilih",
        // bukan halaman error yang membocorkan keberadaan catatan itu.
        $selectedId = (int) $request->query('note');

        $selected = $selectedId > 0
            ? WorkNote::query()->ownedBy($user)->find($selectedId)
            : null;

        // Tanpa pilihan, tampilkan catatan teratas agar halaman tidak kosong
        // hanya karena URL belum memuat id.
        $selected ??= WorkNote::query()->ownedBy($user)->ordered()->first();

        return view('notes.index', [
            'notes' => $this->notes->listFor($user, $search, $tagId),
            'trashed' => $this->notes->trashedFor($user),
            'tags' => $this->notes->tagsFor($user),
            'selected' => $selected,
            'search' => $search,
            'tagId' => $tagId,
            'autosaveDelay' => (int) config('notes.autosave_debounce_ms'),
            'bodyLimit' => (int) config('notes.max_length'),
        ]);
    }

    /** Membuat catatan baru; autosave mengirim `wantsJson`. */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorize('create', WorkNote::class);

        $data = $request->validate($this->rules());

        $note = $this->notes->create($request->user(), $data);

        if ($request->wantsJson()) {
            return response()->json([
                'note' => $this->payload($note),
                'message' => 'Catatan baru dibuat.',
            ], 201);
        }

        return redirect()
            ->route('notes.index', ['note' => $note->getKey()])
            ->with('success', 'Catatan baru dibuat.');
    }

    /**
     * Menyimpan isi catatan (autosave).
     *
     * `lock_version` dikirim client; bila tidak cocok, server menolak (409).
     * Penolakan itu berupa exception tersendiri (`NoteConflictException`) agar
     * sisi klien dapat membedakan "isi tidak sah" dari "catatan sudah berubah".
     */
    public function update(Request $request, WorkNote $note): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $note);

        $data = $request->validate($this->rules() + [
            'lock_version' => ['nullable', 'integer', 'min:1'],
        ]);

        $note = $this->notes->saveChanges(
            $note,
            $data,
            $request->filled('lock_version') ? (int) $data['lock_version'] : null,
        );

        if ($request->wantsJson()) {
            return response()->json([
                'note' => $this->payload($note),
                // Waktu server dipakai sebagai penanda "tersimpan", sehingga
                // jam PC yang salah tidak membuat labelnya menyesatkan.
                'saved_at' => now()->format('H:i'),
            ]);
        }

        return back()->with('success', 'Catatan disimpan.');
    }

    /** Sematkan / lepas sematan. */
    public function pin(Request $request, WorkNote $note): JsonResponse|RedirectResponse
    {
        $this->authorize('pin', $note);

        $note = $this->notes->togglePinned($note);

        if ($request->wantsJson()) {
            return response()->json(['note' => $this->payload($note)]);
        }

        return back()->with('success', $note->pinned ? 'Catatan disematkan.' : 'Sematan dilepas.');
    }

    /** Pindahkan ke tong sampah (soft delete). */
    public function destroy(Request $request, WorkNote $note): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $note);

        $this->notes->trash($note);

        if ($request->wantsJson()) {
            return response()->json([
                'deleted' => $note->getKey(),
                'message' => 'Catatan dipindahkan ke tong sampah. Masih bisa dipulihkan.',
            ]);
        }

        return back()->with('success', 'Catatan dipindahkan ke tong sampah. Masih bisa dipulihkan.');
    }

    /**
     * Memulihkan catatan dari tong sampah.
     *
     * Route memakai `withTrashed()` sehingga catatan yang sudah dihapus masih
     * dapat ditemukan. Bila route binding biasa dipakai, pemulihan tidak akan
     * pernah berhasil karena modelnya dianggap tidak ada (404).
     */
    public function restore(Request $request, WorkNote $note): JsonResponse|RedirectResponse
    {
        $this->authorize('restore', $note);

        $note = $this->notes->restore($note);

        if ($request->wantsJson()) {
            return response()->json(['note' => $this->payload($note)]);
        }

        return back()->with('success', 'Catatan dipulihkan.');
    }

    /**
     * Mengunduh SATU catatan sebagai berkas (PRD Catatan §14).
     *
     * Otorisasi memakai `view` pada catatan - bukan permission - karena
     * catatan pribadi hanya boleh dibaca pemiliknya (lihat WorkNotePolicy).
     */
    public function export(Request $request, WorkNote $note): StreamedResponse
    {
        $this->authorize('view', $note);

        $format = (string) $request->query('format', WorkNoteService::EXPORT_TEXT);
        [$filename, $content] = $this->notes->exportNote($note, $format);

        return $this->download($filename, $content);
    }

    /**
     * Mengunduh SELURUH catatan aktif milik user sebagai satu berkas.
     *
     * Tidak ada model di route: yang diekspor selalu catatan milik pengguna
     * yang sedang login, jadi tidak ada id yang dapat dipakai membidik orang
     * lain.
     */
    public function exportAll(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', WorkNote::class);

        $format = (string) $request->query('format', WorkNoteService::EXPORT_TEXT);
        [$filename, $content] = $this->notes->exportAll($request->user(), $format);

        return $this->download($filename, $content);
    }

    /**
     * Kirim berkas sebagai unduhan teks.
     *
     * `Content-Type` menyertakan charset UTF-8 supaya judul/isi beraksen tidak
     * rusak saat dibuka di aplikasi lain; nama berkas dinetralkan service.
     */
    private function download(string $filename, string $content): StreamedResponse
    {
        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $filename, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    /** Membuat label baru (atau mengembalikan yang sudah ada). */
    public function storeTag(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorize('create', WorkNote::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:' . (int) config('notes.tag_max_length')],
        ], [
            'name.required' => 'Nama label tidak boleh kosong.',
        ]);

        $tag = $this->notes->findOrCreateTag($request->user(), $data['name']);

        if ($request->wantsJson()) {
            return response()->json(['tag' => $this->tagPayload($tag)], 201);
        }

        return back()->with('success', 'Label dibuat.');
    }

    /**
     * Menghapus label milik sendiri.
     *
     * Otorisasi diperiksa di sini (bukan lewat policy model terpisah) karena
     * aturannya sama dengan seluruh modul: pemilik saja. Tautan ke catatan
     * ikut lepas lewat cascade, catatannya sendiri tidak tersentuh.
     */
    public function destroyTag(Request $request, NoteTag $tag): JsonResponse|RedirectResponse
    {
        abort_unless($tag->user_id === $request->user()->getKey(), 403);

        $this->notes->deleteTag($tag);

        if ($request->wantsJson()) {
            return response()->json(['deleted' => $tag->getKey()]);
        }

        return back()->with('success', 'Label dihapus.');
    }

    /** Mengganti seluruh label sebuah catatan (daftar utuh dari panel label). */
    public function syncTags(Request $request, WorkNote $note): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $note);

        $data = $request->validate([
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer'],
        ]);

        $note = $this->notes->syncTags($note, $request->user(), $data['tags'] ?? []);

        if ($request->wantsJson()) {
            return response()->json(['note' => $this->payload($note)]);
        }

        return back()->with('success', 'Label catatan diperbarui.');
    }

    /** Aturan validasi; batas panjang dibaca dari config, bukan angka tetap. */    private function rules(): array
    {
        $bodyLimit = (int) config('notes.max_length');
        $titleLimit = (int) config('notes.title_max_length');

        return [
            'title' => ['nullable', 'string', 'max:' . $titleLimit],
            // `body` tidak wajib: autosave mengirimkan ketikan yang belum tentu
            // lengkap. Yang dijaga adalah batas atasnya.
            'body' => ['nullable', 'string', 'max:' . $bodyLimit],
        ];
    }

    /**
     * Bentuk data catatan untuk sisi klien (autosave).
     *
     * Mengirim `lock_version` hasil simpan supaya tab yang sama dapat
     * melanjutkan penyuntingan tanpa memuat ulang halaman.
     */
    private function payload(WorkNote $note): array
    {
        return [
            'id' => $note->getKey(),
            'title' => $note->title,
            'body' => $note->body,
            'pinned' => (bool) $note->pinned,
            'display_title' => $note->display_title,
            'lock_version' => (int) $note->lock_version,
            'last_edited_at' => $note->last_edited_at?->format('d M Y H:i'),
            'tags' => $note->tags->map(fn (NoteTag $tag) => $this->tagPayload($tag))->all(),
        ];
    }

    /** Bentuk data label untuk sisi klien. */
    private function tagPayload(NoteTag $tag): array
    {
        return [
            'id' => $tag->getKey(),
            'name' => $tag->name,
        ];
    }
}
