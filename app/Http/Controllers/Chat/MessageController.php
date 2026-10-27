<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Halaman percakapan + kirim pesan + polling (PRD Chat §5/§8/§9).
 *
 * Semua aksi di sini dijaga ConversationPolicy: otorisasi tidak boleh
 * bergantung pada URL, karena id percakapan mudah ditebak.
 *
 * AGENT: asisten | 2026-10-06 | aksi pesan lanjutan (sunting/hapus/semat/cari/utas) | modul Chat §15
 */
class MessageController extends Controller
{
    public function __construct(private readonly ChatService $chat) {}

    /**
     * Buka sebuah percakapan.
     *
     * Membuka percakapan SEKALIGUS menandainya sudah dibaca (PRD §9). Ini
     * disengaja walaupun artinya badge bisa hilang lebih cepat daripada
     * "selesai membaca": perilakunya sederhana dan dapat diprediksi.
     */
    public function show(Request $request, Conversation $conversation): View
    {
        $this->authorize('view', $conversation);

        $user = $request->user();

        $conversation->load(['members']);

        $messages = $this->chat->recentMessages($conversation);

        $this->chat->markRead($conversation, $user);

        $canManageMembers = $user->can('manageMembers', $conversation);

        // Kandidat anggota hanya dihitung untuk pihak yang boleh mengelola,
        // supaya daftar seluruh karyawan tidak dirender ke semua orang.
        $candidates = $canManageMembers
            ? User::query()
                ->where('status', 'active')
                ->whereKeyNot($conversation->members->modelKeys())
                ->orderBy('name')
                ->get()
            : collect();

        // Pesan yang disematkan ditampilkan sebagai bilah di atas riwayat
        // (PRD §15). Diambil terpisah dari 50 pesan terakhir supaya sematan
        // lama tetap terlihat walau sudah tergulir jauh ke belakang.
        $pinned = $this->chat->pinnedMessages($conversation);

        // Daftar percakapan untuk panel samping. Dihitung SETELAH markRead()
        // supaya angka "belum dibaca" percakapan yang sedang dibuka sudah
        // nol — panel samping tidak boleh menandai percakapan ini sebagai
        // belum dibaca saat sedang dilihat.
        $conversations = $this->chat->forUser($user);
        $unread = $this->chat->unreadCounts($user, $conversations->pluck('id')->all());

        return view('chat.show', [
            'conversation' => $conversation,
            'messages' => $messages,
            'pinned' => $pinned,
            'conversations' => $conversations,
            'unread' => $unread,
            'canManageMembers' => $canManageMembers,
            'candidates' => $candidates,
            'messageMaxLength' => (int) config('chat.message_max_length', 2000),
            'attachmentMaxKb' => (int) config('chat.attachment_max_kb', 25600),
            'pollInterval' => (int) config('chat.poll.messages', 5000),
        ]);
    }

    /** Kirim pesan (+ lampiran). */
    public function store(Request $request, Conversation $conversation): RedirectResponse|JsonResponse
    {
        $this->authorize('sendMessage', $conversation);

        $maxLength = (int) config('chat.message_max_length', 2000);

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:' . $maxLength],
            'parent_id' => ['nullable', 'integer'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => [
                'file',
                'max:' . (int) config('chat.attachment_max_kb', 25600),
                'mimes:' . implode(',', config('chat.attachment_extensions', [])),
            ],
        ], [
            'body.max' => 'Pesan terlalu panjang (maksimal ' . $maxLength . ' karakter).',
            'attachments.*.mimes' => 'Tipe berkas tidak diizinkan.',
            'attachments.*.max' => 'Ukuran berkas terlalu besar.',
        ]);

        $this->chat->send(
            $conversation,
            $request->user(),
            (string) ($data['body'] ?? ''),
            $request->file('attachments') ?? [],
            isset($data['parent_id']) ? (int) $data['parent_id'] : null,
        );

        // Endpoint yang sama melayani formulir biasa (redirect) dan kiriman
        // AJAX dari halaman percakapan (JSON), supaya halaman tidak berkedip.
        if ($request->expectsJson()) {
            return response()->json(['status' => 'ok']);
        }

        return back()->with('success', 'Pesan terkirim.');
    }

    /**
     * Polling pesan baru sejak id tertentu (PRD §9).
     *
     * Balasan berupa HTML potongan (bukan JSON berisi model) supaya
     * penandaannya sama persis dengan pesan yang dirender server saat
     * halaman pertama dibuka — satu partial, satu sumber kebenaran.
     *
     * `markRead()` hanya dipanggil bila poll ini benar-benar membawa pesan
     * baru, atau bila jeda throttle sudah terlewati (rencana B1). Sebelumnya
     * setiap poll menulis `last_read_at` — dua tulisan setiap 5 detik per
     * pengguna aktif, padahal mayoritas poll kosong.
     */
    public function poll(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $afterId = (int) $request->query('after', 0);

        $messages = $this->chat->messagesAfter($conversation, $afterId);

        $this->chat->markReadIfDue($conversation, $request->user(), $messages->count());

        return response()->json([
            'html' => $messages
                ->map(fn ($message) => view('chat._message', [
                    'message' => $message,
                    'conversation' => $conversation,
                ])->render())
                ->implode(''),
            'last_id' => $messages->last()?->id ?? $afterId,
            'count' => $messages->count(),
        ]);
    }

    /**
     * Memuat pesan lama sebelum sebuah id (rencana B3).
     *
     * `recentMessages()` hanya mengambil 50 pesan terbaru. Untuk membaca
     * percakapan yang lebih panjang, pengguna memuat halaman lama secara
     * bertahap; setiap permintaan membawa id pesan TERKECIL yang sudah
     * dimuat, dan endpoint ini mengembalikan 50 pesan sebelumnya.
     *
     * Urutan yang dikembalikan sama dengan `poll()`: kronologis (termuda di
     * akhir). Frontend menempelkannya di ATAS daftar pesan yang sudah ada.
     *
     * Lampiran ikut dimuat karena pesan lama bisa berupa dokumen gambar.
     *
     * Id pesan dikirim sebagai parameter PATH (`/pesan/sebelum/{message}`),
     * bukan query string, sesuai rencana B3.
     *
     * Keanggotaan tetap dijaga ConversationPolicy::view. Id pesan di sini
     * hanyalah penanda batas yang dikirim sendiri oleh klien, jadi tidak ada
     * kerentanan tampering: bila pengguna mengirim id dari percakapan lain,
     * `messagesBefore()` tetap hanya memilih pesan `$conversation` ini dan
     * klien hanya melihat pesan milik percakapannya sendiri.
     */
    public function before(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        $this->authorize('view', $conversation);

        // Pesan menjadi pegangan saja: id dari percakapan lain tidak boleh
        // diterima. Query sudah memfilter per percakapan sehingga tidak ada
        // kebocoran data, tetapi permintaan seperti itu tetap ditolak
        // tegas (404) supaya perilaku endpoint konsisten dan tidak bisa
        // dijadikan oracle untuk menebak keberadaan pesan asing.
        abort_unless(
            (int) $message->conversation_id === (int) $conversation->getKey(),
            404,
        );

        $messages = $this->chat->messagesBefore($conversation, (int) $message->id);

        return response()->json([
            'html' => $messages
                ->map(fn ($message) => view('chat._message', [
                    'message' => $message,
                    'conversation' => $conversation,
                ])->render())
                ->implode(''),
            'count' => $messages->count(),
            'has_more' => $messages->isNotEmpty(),
        ]);
    }

    /**
     * Sunting isi pesan (PRD §15).
     *
     * Otorisasi memakai `updateMessage` pada Message — bukan pada conversation —
     * karena yang menentukan di sini bukan "apakah saya anggota", melainkan
     * "apakah saya penulis pesan ini".
     */
    public function update(Request $request, Conversation $conversation, Message $message): RedirectResponse|JsonResponse
    {
        $this->authorize('view', $conversation);
        $this->assertMessageBelongsTo($conversation, $message);
        $this->authorize('update', $message);

        $maxLength = (int) config('chat.message_max_length', 2000);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:' . $maxLength],
        ], [
            'body.required' => 'Isi pesan tidak boleh kosong.',
            'body.max' => 'Pesan terlalu panjang (maksimal ' . $maxLength . ' karakter).',
        ]);

        $message = $this->chat->editMessage($message, $data['body']);

        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('chat._message', [
                    'message' => $message,
                    'conversation' => $conversation,
                    'suppressSeparator' => true,
                ])->render(),
            ]);
        }

        return back()->with('success', 'Pesan diperbarui.');
    }

    /** Hapus pesan (soft delete; PRD §15). */
    public function destroy(Request $request, Conversation $conversation, Message $message): RedirectResponse|JsonResponse
    {
        $this->authorize('view', $conversation);
        $this->assertMessageBelongsTo($conversation, $message);
        $this->authorize('delete', $message);

        $this->chat->deleteMessage($message);

        if ($request->expectsJson()) {
            return response()->json(['status' => 'ok', 'id' => $message->getKey()]);
        }

        return back()->with('success', 'Pesan dihapus.');
    }

    /** Sematkan / lepas sematan pesan (PRD §15). */
    public function pin(Request $request, Conversation $conversation, Message $message): RedirectResponse|JsonResponse
    {
        $this->authorize('view', $conversation);
        $this->assertMessageBelongsTo($conversation, $message);
        $this->authorize('pin', $message);

        $pinned = $this->chat->togglePinned($message);

        if ($request->expectsJson()) {
            return response()->json(['status' => 'ok', 'pinned' => $pinned]);
        }

        return back()->with('success', $pinned ? 'Pesan disematkan.' : 'Sematan pesan dilepas.');
    }

    /**
     * Cari pesan di dalam percakapan (PRD §15).
     *
     * Hasil dikirim sebagai HTML potongan yang sama dengan polling, sehingga
     * tampilan hasil pencarian identik dengan tampilan riwayat.
     */
    public function search(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $term = (string) $request->query('q', '');

        $messages = $this->chat->searchMessages($conversation, $term);

        return response()->json([
            'html' => $messages
                ->map(fn ($message) => view('chat._message', [
                    'message' => $message,
                    'conversation' => $conversation,
                    'suppressSeparator' => true,
                ])->render())
                ->implode(''),
            'count' => $messages->count(),
            'term' => $term,
        ]);
    }

    /** Balasan-balasan sebuah pesan (utas; PRD §15). */
    public function thread(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        $this->authorize('view', $conversation);
        $this->assertMessageBelongsTo($conversation, $message);

        $limit = (int) config('chat.thread_preview_limit', 20);

        $replies = $this->chat->threadReplies($message, $limit);

        return response()->json([
            'parent' => view('chat._message', [
                'message' => $message->load('user')->loadCount('replies'),
                'conversation' => $conversation,
                'suppressSeparator' => true,
            ])->render(),
            'html' => $replies
                ->map(fn ($reply) => view('chat._message', [
                    'message' => $reply,
                    'conversation' => $conversation,
                    'suppressSeparator' => true,
                ])->render())
                ->implode(''),
            'count' => $replies->count(),
            'has_more' => $replies->count() >= $limit,
        ]);
    }

    /**
     * Tolak pesan milik percakapan lain dengan 404.
     *
     * Sama seperti pada `before()`: query sudah memfilter per percakapan,
     * tetapi permintaan dengan id asing ditolak tegas supaya endpoint tidak
     * dapat dipakai menebak keberadaan pesan percakapan lain.
     */
    private function assertMessageBelongsTo(Conversation $conversation, Message $message): void
    {
        abort_unless(
            (int) $message->conversation_id === (int) $conversation->getKey(),
            404,
        );
    }

    /**
     * Unduh / tampilkan lampiran.
     *
     * Dua penjagaan: pengguna harus anggota percakapan (policy `view`), dan
     * berkasnya dibaca dari disk privat. Tidak ada URL `storage/...` untuk
     * chat, jadi pemilik tautan yang bukan anggota tetap ditolak.
     */
    public function attachment(Request $request, MessageAttachment $attachment): StreamedResponse
    {
        $message = $attachment->message()->with('conversation')->firstOrFail();

        $this->authorize('view', $message->conversation);

        $path = $this->chat->attachmentPath($attachment);

        // Gambar ditampilkan langsung (inline) agar bisa disematkan lewat
        // route yang tetap memeriksa keanggotaan; dokumen diunduh.
        $inline = $attachment->isImage() && $request->boolean('inline');

        return Storage::disk(ChatService::DISK)->response(
            $path,
            $attachment->original_name,
            [],
            $inline ? 'inline' : 'attachment',
        );
    }
}
