<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\ChatService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Daftar percakapan & pengelolaan kanal (PRD Chat §5/§6).
 *
 * Halaman daftar sengaja memuat dua hal sekaligus: daftar percakapan yang
 * diikuti user, dan (hanya untuk admin/qa) pintu masuk pembuatan kanal.
 * Memisahkannya menjadi halaman terpisah hanya menambah langkah tanpa
 * menambah kejelasan, karena keduanya dibuka dari tempat yang sama.
 *
 * AGENT: asisten | 2026-10-10 | tambah endpoint polling badge + daftar percakapan | modul Chat §9
 */
class ConversationController extends Controller
{
    public function __construct(
        private readonly ChatService $chat,
        private readonly AuditLogService $audit,
        private readonly NotificationService $notifications,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Conversation::class);

        // Daftar karyawan hanya dibutuhkan pihak yang boleh membuat kanal.
        // Dicek lewat permission, bukan lewat nama role, agar konsisten dengan
        // ConversationPolicy.
        $users = $request->user()->can('channel.create')
            ? User::query()->where('status', 'active')->orderBy('name')->get()
            : collect();

        return view('chat.index', array_merge($this->listData($request), [
            'users' => $users,
        ]));
    }

    /**
     * Data untuk SATU tampilan daftar percakapan.
     *
     * Dipakai halaman penuh (`index`) dan endpoint polling (`poll`) supaya
     * baris yang datang lewat polling identik dengan yang dirender server saat
     * halaman pertama dibuka. Dua salinan logika pasti perlahan menyimpang —
     * alasan yang sama dengan mengapa markup barisnya sendiri ada di
     * `chat/_conversation_list`.
     *
     * @return array{conversations: LengthAwarePaginator, unread: array<int,int>, search: string|null}
     */
    private function listData(Request $request): array
    {
        $user = $request->user();
        $search = $request->query('search');

        $conversations = $this->chat->forUser($user, $search);

        return [
            'conversations' => $conversations,
            // Angka belum dibaca dihitung untuk SELURUH halaman dalam satu
            // query, bukan satu query per baris (N+1).
            'unread' => $this->chat->unreadCounts($user, $conversations->pluck('id')->all()),
            'search' => $search,
        ];
    }

    /**
     * Jumlah "belum dibaca" untuk badge sidebar (chat) & topbar (notifikasi)
     * — PRD Chat §9, target polling pertama.
     *
     * Badge ini tampil di SELURUH halaman, jadi angkanya sengaja dipisah dari
     * halaman chat: klien dapat menyegarkannya tanpa memuat ulang halaman apa
     * pun. Sebelumnya angka ini hanya dihitung saat server merender halaman
     * (composer di AppServiceProvider), sehingga penerima yang tidak membuka
     * percakapan itu baru melihat perubahannya setelah memuat ulang.
     *
     * Dua angka digabung dalam SATU respons supaya satu siklus polling cukup
     * memakai satu permintaan. Otorisasi tidak diperlukan di sini: keduanya
     * hanya menghitung baris milik pengguna yang sedang masuk, bukan data
     * percakapan siapa pun.
     */
    public function unread(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            // Dijaga `Schema::hasTable()` persis seperti composer sidebar: bila
            // kode sudah diperbarui tetapi migration chat belum dijalankan
            // (mis. deploy bertahap), badge sekadar bernilai nol — bukan 500.
            'chat' => Schema::hasTable('conversation_members')
                ? $this->chat->totalUnread($user)
                : 0,
            'notifikasi' => $this->notifications->unreadCount($user),
        ]);
    }

    /**
     * Potongan HTML daftar percakapan untuk polling (PRD Chat §9, target
     * ketiga).
     *
     * Mengembalikan HTML — bukan JSON berisi data — dengan alasan yang sama
     * seperti polling pesan: baris hasil polling harus identik dengan yang
     * dirender server, dan klien cukup menukar satu simpul
     * (`[data-chat-conversation-list]`). Halaman daftar penuh adalah
     * satu-satunya pemanggilnya; panel samping di halaman percakapan sengaja
     * TIDAK ikut ditukar karena sorotan baris aktif dan posisi gulirnya
     * dikelola JavaScript di sana.
     *
     * `search` dan `page` diteruskan apa adanya dari query string halaman,
     * jadi yang disegarkan adalah tampilan yang SEDANG dilihat pengguna, bukan
     * selalu halaman pertama.
     */
    public function poll(Request $request): Response
    {
        $this->authorize('viewAny', Conversation::class);

        return response()->view('chat._conversation_list', $this->listData($request));
    }

    /** Buat kanal baru — hanya admin/qa (PRD §6). */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Conversation::class);

        $data = $request->validate([
            // Nama kanal unik (rencana B6). Pemeriksaan case-insensitive:
            // produksi (MySQL, collation `utf8mb4_unicode_ci`) sudah
            // case-insensitive, sehingga 'Tim QA' dan 'TIM QA' dianggap sama.
            // SQLite (pengujian) case-sensitive, jadi di lingkungan tes
            // pemeriksaan ini memakai perbandingan persis; indeks unik di
            // migrasi B6 menjadi penjaga terakhir di MySQL.
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('conversations', 'name')->where(fn ($query) => $query->where('type', 'channel')),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'is_private' => ['nullable', 'boolean'],
            'members' => ['nullable', 'array'],
            'members.*' => ['integer', Rule::exists('users', 'id')],
        ], [
            'name.required' => 'Nama kanal wajib diisi.',
            'name.unique' => 'Nama kanal sudah digunakan. Pilih nama lain.',
        ]);

        $conversation = $this->chat->createChannel(
            $request->user(),
            $data,
            $data['members'] ?? [],
        );

        $this->audit->log('chat.channel.create', $conversation, [], [
            'name' => $conversation->name,
            'is_private' => $conversation->is_private,
            'members' => $conversation->members()->count(),
        ]);

        return redirect()
            ->route('chat.show', $conversation)
            ->with('success', "Kanal {$conversation->name} berhasil dibuat.");
    }

    public function update(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('update', $conversation);

        $data = $request->validate([
            // Aturan keunikan nama sama dengan `store()`, tetapi kanal ini
            // sendiri dikecualikan (`ignore`) supaya mengubah deskripsinya
            // tanpa mengganti nama tidak dianggap bentrok dengan dirinya.
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('conversations', 'name')
                    ->where(fn ($query) => $query->where('type', 'channel'))
                    ->ignore($conversation->getKey()),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'is_private' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'Nama kanal sudah digunakan. Pilih nama lain.',
        ]);

        $before = [
            'name' => $conversation->name,
            'is_private' => $conversation->is_private,
        ];

        $this->chat->updateChannel($conversation, $data);

        $this->audit->log('chat.channel.update', $conversation, $before, [
            'name' => $conversation->name,
            'is_private' => $conversation->is_private,
        ]);

        return redirect()
            ->route('chat.show', $conversation)
            ->with('success', 'Kanal berhasil diperbarui.');
    }

    /**
     * Arsipkan kanal (PRD §6) — bukan penghapusan permanen.
     *
     * Riwayat pesan tetap ada di database; yang berubah hanya apakah kanal
     * masih muncul pada daftar aktif dan masih dapat dibuka.
     */
    public function archive(Conversation $conversation): RedirectResponse
    {
        $this->authorize('archive', $conversation);

        $name = $conversation->name;

        $this->chat->archive($conversation);

        $this->audit->log('chat.channel.archive', $conversation, [], ['name' => $name]);

        return redirect()
            ->route('chat.index')
            ->with('success', "Kanal {$name} diarsipkan. Riwayatnya tetap tersimpan.");
    }

    /** Tambah anggota kanal (manual — PRD §6). */
    public function addMembers(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('manageMembers', $conversation);

        $data = $request->validate([
            'members' => ['required', 'array', 'min:1'],
            'members.*' => ['integer', Rule::exists('users', 'id')],
        ], [
            'members.required' => 'Pilih minimal satu karyawan.',
        ]);

        $added = $this->chat->addMembers($conversation, $data['members']);

        $this->audit->log('chat.members.add', $conversation, [], ['added' => $added]);

        return back()->with(
            'success',
            $added > 0 ? "{$added} anggota ditambahkan." : 'Anggota tersebut sudah ada di kanal.',
        );
    }

    /** Keluarkan anggota dari kanal (manual — PRD §6). */
    public function removeMember(Conversation $conversation, User $user): RedirectResponse
    {
        $this->authorize('manageMembers', $conversation);

        // Pembuat kanal tidak dapat dikeluarkan (dijaga di ChatService),
        // sehingga kanal tidak pernah kosong tanpa disengaja.
        $removed = $this->chat->removeMembers($conversation, [$user]);

        $this->audit->log('chat.members.remove', $conversation, ['user_id' => $user->getKey()], []);

        return back()->with(
            'success',
            $removed > 0 ? "{$user->name} dikeluarkan dari kanal." : 'Tidak ada anggota yang dihapus.',
        );
    }
}
