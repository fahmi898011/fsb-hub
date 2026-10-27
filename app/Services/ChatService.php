<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use App\Traits\SanitizesSearchTerm;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Logika modul Chat yang tidak bergantung HTTP (PRD Chat §3-§9).
 *
 * Ditaruh di service — bukan controller — supaya aturan penting (anti DM
 * ganda, keanggotaan manual, disk privat, `last_read_at`) punya satu tempat
 * dan dapat diuji tanpa merender halaman.
 *
 * AGENT: asisten | 2026-10-06 | tambah send(?parentId) + edit/delete/pin/cari/utas | modul Chat §15
 */
class ChatService
{
    use SanitizesSearchTerm;

    /** Disk PRIVAT untuk lampiran (PRD §8). Bukan `public`. */
    public const DISK = 'local';

    public const ATTACHMENT_FOLDER = 'chat';

    /**
     * Jeda minimum (detik) antara dua tulisan `last_read_at` dari polling
     * pada percakapan yang sama (rencana B1).
     *
     * Angka 15 detik sengaja lebih PANJANG dari interval polling (config
     * `chat.poll.messages`, default 5 detik): bila jeda ini lebih pendek dari
     * interval polling, throttle tidak pernah aktif. Dengan 15 detik, hanya
     * poll ke-3 atau ke-4 yang benar-benar menulis saat tidak ada pesan baru.
     *
     * Mengapa bukan middleware `throttle:` di route: throttle middleware
     * membalas 429 dan menghentikan polling — padahal polling harus tetap
     * mengembalikan pesan baru. Yang ditunda di sini hanya tulisan
     * `last_read_at`, bukan seluruh response.
     *
     * Angka ini tidak boleh melebihi `chat.viewing_window_seconds` (default
     * 15 detik, lihat ChatNotifier): selama tab masih terbuka dan polling
     * masih jalan, `last_read_at` harus diperbarui setidaknya sekali sebelum
     * jendela "sedang membuka percakapan" berakhir, jika tidak pengguna
     * menerima notifikasi untuk percakapan yang sedang dilihatnya. 10 detik
     * memberi margin di bawah 15 detik dan tetap memotong mayoritas tulisan.
     */
    public const MARK_READ_THROTTLE_SECONDS = 10;

    public function __construct(private readonly ChatNotifier $notifier) {}

    /* ------------------------------------------------------------------ */
    /* Daftar percakapan                                                   */
    /* ------------------------------------------------------------------ */

    /**
     * Daftar percakapan yang diikuti user, terbaru lebih dulu.
     *
     * Urutan memakai `last_message_at` (rencana B2) — kolom yang dijaga saat
     * pesan dikirim — dengan `created_at` sebagai cadangan untuk percakapan
     * tanpa pesan (mis. kanal yang baru dibuat). Sebelumnya ini memakai
     * subquery `COALESCE((select max(created_at) ...))` per baris terhadap
     * seluruh tabel `messages`; sekarang hanya membaca index komposit
     * `(last_message_at, created_at)`.
     */
    public function forUser(User $user, ?string $search = null): LengthAwarePaginator
    {
        // Karakter wildcard SQL dibuang lewat trait yang sama dengan
        // pencarian informasi, supaya aturannya satu sumber.
        $term = $this->sanitizeSearchTerm($search);

        return Conversation::query()
            ->forUser($user)
            ->active()
            ->with(['members', 'latestMessage.user'])
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($inner) use ($term) {
                    // Kanal dicari dari namanya; DM dicari dari nama anggota
                    // (judul DM memang diturunkan dari lawan bicara).
                    $inner->where('name', 'like', "%{$term}%")
                        ->orWhereHas('members', fn ($m) => $m->where('name', 'like', "%{$term}%"))
                        ->orWhereHas('messages', fn ($m) => $m->where('body', 'like', "%{$term}%"));
                });
            })
            ->orderByRaw('COALESCE(conversations.last_message_at, conversations.created_at) DESC')
            ->orderBy('id', 'desc')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * Jumlah pesan belum dibaca per percakapan, untuk banyak percakapan
     * sekaligus dalam SATU query.
     *
     * Perbandingan waktunya dilakukan di database: pesan dihitung belum
     * dibaca bila `created_at`-nya lebih baru daripada `last_read_at`
     * anggota. Dengan begitu tidak ada status "belum dibaca" yang disimpan
     * (dan bisa basi) — selalu dihitung dari kenyataan.
     *
     * @param  array<int, int>|null  $conversationIds  null = semua percakapan user
     * @return array<int, int>  key = id percakapan
     */
    public function unreadCounts(User $user, ?array $conversationIds = null): array
    {
        $query = DB::table('messages')
            ->join('conversation_members', function ($join) use ($user) {
                $join->on('conversation_members.conversation_id', '=', 'messages.conversation_id')
                    ->where('conversation_members.user_id', '=', $user->getKey());
            })
            ->whereNull('messages.deleted_at')
            ->where('messages.user_id', '!=', $user->getKey())
            ->where(function ($inner) {
                $inner->whereNull('conversation_members.last_read_at')
                    ->orWhereColumn('messages.created_at', '>', 'conversation_members.last_read_at');
            })
            ->groupBy('messages.conversation_id')
            ->selectRaw('messages.conversation_id as conversation_id, count(*) as total');

        if ($conversationIds !== null) {
            if ($conversationIds === []) {
                return [];
            }

            $query->whereIn('messages.conversation_id', $conversationIds);
        }

        return $query->pluck('total', 'conversation_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /** Total pesan belum dibaca di seluruh percakapan user (untuk badge). */
    public function totalUnread(User $user): int
    {
        return array_sum($this->unreadCounts($user));
    }

    /* ------------------------------------------------------------------ */
    /* DM                                                                  */
    /* ------------------------------------------------------------------ */

    /**
     * Cari percakapan DM antara dua orang, atau buat baru.
     *
     * Anti DM ganda (PRD §4): pasangan diuji dengan dua kondisi `whereHas`
     * sehingga urutan pembuatan tidak berpengaruh — pasangan (A,B) dan (B,A)
     * menghasilkan percakapan yang sama.
     *
     * Perlindungan race condition (rencana B4): pemeriksaan di atas tidak
     * cukup untuk dua request paralel (klik ganda pada "Mulai Chat", atau
     * dua tab yang dibuka bersamaan). Tiga lapis perlindungan:
     *
     * 1. Transaksi + `lockForUpdate()` (lihat `findDirect()`) membuat SELECT
     *    dan INSERT tidak bisa disisipi transaksi lain.
     * 2. Indeks unik `(type, direct_pair)` di `conversations` (migrasi B4)
     *    adalah penghalang terakhir: database sendiri yang menjaganya, jadi
     *    tidak bergantung pada urutan eksekusi aplikasi.
     * 3. Pelanggaran unique ditangkap: bila dua transaksi tetap lolos (mis.
     *    dua koneksi database terpisah), exceptionnya ditangkap dan
     *    percakapan yang sudah ada dikembalikan — bukan error 500.
     */
    public function findOrCreateDirect(User $user, User $target): Conversation
    {
        $existing = $this->findDirect($user, $target);

        if ($existing) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($user, $target) {
                // Di dalam transaksi: kunci baris DM yang ada agar transaksi
                // paralel MENUNGGU, bukan membuat percakapannya sendiri.
                $existing = $this->findDirect($user, $target, true);

                if ($existing) {
                    return $existing;
                }

                // `created_by` diisi salah satu peserta agar kolom itu tidak
                // pernah menunjuk orang di luar percakapan. Sang pembuat TIDAK
                // mendapat keistimewaan apa pun: tidak ada moderator DM.
                $conversation = Conversation::create([
                    'type' => Conversation::TYPE_DIRECT,
                    'created_by' => $user->getKey(),
                    'direct_pair' => Conversation::directPairFor([$user, $target]),
                ]);

                $this->addMembers($conversation, [$user, $target]);

                return $conversation;
            });
        } catch (QueryException $e) {
            // Relai: transaksi paralel memenangkan indeks unik (kode 23000
            // = integrity constraint violation). Percakapan yang dibuatnya
            // sudah ada — kembalikan, jangan error.
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            return $this->findDirect($user, $target);
        }
    }

    /**
     * Cari percakapan DM antara dua orang.
     *
     * @param  bool  $lock  true untuk `lockForUpdate()` (hanya di dalam transaksi)
     */
    private function findDirect(User $user, User $target, bool $lock = false): ?Conversation
    {
        $query = Conversation::query()
            ->direct()
            ->whereHas('members', fn ($q) => $q->whereKey($user->getKey()))
            ->whereHas('members', fn ($q) => $q->whereKey($target->getKey()));

        if ($lock) {
            // `lockForUpdate` menutup celah antara SELECT dan INSERT:
            // transaksi kedua menunggu sampai transaksi pertama commit,
            // lalu melihat baris yang baru saja dibuat.
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /* ------------------------------------------------------------------ */
    /* Kanal                                                               */
    /* ------------------------------------------------------------------ */

    /**
     * Buat kanal baru (PRD §6).
     *
     * Pembuat otomatis menjadi anggota pertama — tanpa itu pembuat kanal
     * tidak dapat membuka kanalnya sendiri.
     *
     * @param  array<int, int|User>  $members
     */
    public function createChannel(User $creator, array $data, array $members = []): Conversation
    {
        return DB::transaction(function () use ($creator, $data, $members) {
            $conversation = Conversation::create([
                'type' => Conversation::TYPE_CHANNEL,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_private' => (bool) ($data['is_private'] ?? false),
                'created_by' => $creator->getKey(),
            ]);

            $this->addMembers($conversation, array_merge([$creator], $members));

            return $conversation;
        });
    }

    /** Perbarui nama/deskripsi/privasi kanal. */
    public function updateChannel(Conversation $conversation, array $data): Conversation
    {
        $conversation->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_private' => (bool) ($data['is_private'] ?? false),
        ]);

        return $conversation;
    }

    /**
     * Arsipkan kanal (PRD §6): bukan penghapusan permanen.
     *
     * Riwayat pesan sengaja TIDAK dihapus, supaya keputusan yang pernah
     * diambil di kanal itu tetap dapat ditelusuri.
     */
    public function archive(Conversation $conversation): void
    {
        if ($conversation->isArchived()) {
            return;
        }

        $conversation->update(['archived_at' => now()]);
    }

    /* ------------------------------------------------------------------ */
    /* Keanggotaan (manual — PRD §6)                                       */
    /* ------------------------------------------------------------------ */

    /**
     * Tambah anggota bila belum ada.
     *
     * Idempoten supaya mengirim ulang form tidak memunculkan error
     * unique constraint.
     *
     * @param  array<int, int|User>  $users
     */
    public function addMembers(Conversation $conversation, array $users): int
    {
        $added = 0;

        foreach ($users as $user) {
            $id = $user instanceof User ? $user->getKey() : (int) $user;

            $exists = ConversationMember::where('conversation_id', $conversation->getKey())
                ->where('user_id', $id)
                ->exists();

            if ($exists) {
                continue;
            }

            ConversationMember::create([
                'conversation_id' => $conversation->getKey(),
                'user_id' => $id,
                'joined_at' => now(),
            ]);

            $added++;
        }

        if ($added > 0) {
            $conversation->unsetRelation('members');
        }

        return $added;
    }

    /**
     * Keluarkan anggota dari kanal.
     *
     * Dua penjagaan: percakapan `direct` tidak boleh diubah keanggotaannya
     * (DM selalu tepat dua orang), dan kanal tidak boleh ditinggalkan tanpa
     * anggota sama sekali. Pembuat kanal dikecualikan agar kanal tidak bisa
     * dikosongkan lewat satu permintaan.
     *
     * @param  array<int, int|User>  $users
     */
    public function removeMembers(Conversation $conversation, array $users): int
    {
        if ($conversation->isDirect()) {
            throw ValidationException::withMessages([
                'members' => 'Keanggotaan percakapan pribadi tidak dapat diubah.',
            ]);
        }

        $ids = collect($users)
            ->map(fn ($user) => $user instanceof User ? $user->getKey() : (int) $user)
            ->reject(fn ($id) => $id === $conversation->created_by)
            ->values()
            ->all();

        if ($ids === []) {
            return 0;
        }

        $remaining = $conversation->members()->whereKeyNot($ids)->count();

        if ($remaining === 0) {
            throw ValidationException::withMessages([
                'members' => 'Kanal harus memiliki minimal satu anggota.',
            ]);
        }

        $deleted = ConversationMember::where('conversation_id', $conversation->getKey())
            ->whereIn('user_id', $ids)
            ->delete();

        $conversation->unsetRelation('members');

        return $deleted;
    }

    /* ------------------------------------------------------------------ */
    /* Pesan                                                               */
    /* ------------------------------------------------------------------ */

    /**
     * Simpan pesan baru beserta lampirannya, lalu picu notifikasi agregat.
     *
     * @param  array<int, UploadedFile>  $files
     * @param  int|null  $parentId  id pesan induk bila ini sebuah balasan (utas)
     */
    public function send(Conversation $conversation, User $sender, string $body, array $files = [], ?int $parentId = null): Message
    {
        $trimmed = trim($body);

        if ($trimmed === '' && $files === []) {
            throw ValidationException::withMessages([
                'body' => 'Pesan tidak boleh kosong.',
            ]);
        }

        // Balasan hanya boleh menunjuk pesan di percakapan yang SAMA. Tanpa
        // pemeriksaan ini, `parent_id` dapat dipakai menautkan utas ke pesan
        // percakapan lain yang tidak boleh dibaca pengirimnya.
        $parent = null;

        if ($parentId !== null) {
            $parent = Message::where('conversation_id', $conversation->getKey())
                ->whereKey($parentId)
                ->first();

            if ($parent === null) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Pesan yang dibalas tidak ditemukan di percakapan ini.',
                ]);
            }
        }

        $message = DB::transaction(function () use ($conversation, $sender, $trimmed, $files, $parent) {
            $message = Message::create([
                'conversation_id' => $conversation->getKey(),
                'user_id' => $sender->getKey(),
                'body' => $trimmed,
                'parent_id' => $parent?->getKey(),
            ]);

            foreach ($files as $file) {
                $this->storeAttachment($message, $file);
            }

            // Waktu pesan terakhir naik untuk mengurutkan daftar percakapan
            // tanpa subquery (rencana B2). Diletakkan di DALAM transaksi
            // bersama pesannya: bila pesan gagal, percakapan tidak boleh
            // menunjukkan waktu pesan yang tidak ada.
            $this->touchLastMessageAt($conversation, $message);

            // Mengirim pesan berarti pengirim sudah membaca percakapannya.
            $this->markRead($conversation, $sender);

            return $message;
        });

        // Notifikasi di LUAR transaksi: kegagalan notifikasi tidak boleh
        // membatalkan pesan yang sudah tersimpan.
        $recipients = $conversation->members()
            ->whereKeyNot($sender->getKey())
            ->get()
            ->all();

        $this->notifier->newMessage($conversation, $message, $sender, $recipients);

        // Sebutan `@` (PRD §15) diberitakan terpisah dari "pesan baru" dengan
        // alasan yang dijelaskan di ChatNotifier::mentions. Daftar anggota
        // yang sama dipakai ulang, jadi tidak ada query tambahan.
        $this->notifier->mentions($conversation, $message, $sender, $recipients);

        // `parent.user` ikut dimuat supaya balasan langsung dapat menampilkan
        // kutipan pesan induk tanpa query tambahan (PRD §15.5).
        return $message->load(['user', 'attachments', 'parent.user']);
    }

    /**
     * Naikkan `last_message_at` ke waktu pesan (rencana B2).
     *
     * `timestamps` dimatikan sementara: `updated_at` percakapan tidak boleh
     * berubah hanya karena ada pesan baru — kolom itu dipakai untuk
     * melacak perubahan pada kanal (nama, deskripsi, arsip).
     */
    private function touchLastMessageAt(Conversation $conversation, Message $message): void
    {
        $conversation->last_message_at = $message->created_at;
        $conversation->timestamps = false;
        $conversation->save();
        $conversation->timestamps = true;
    }

    /** Lampiran disimpan di disk privat; URL publik tidak dibuat (PRD §8). */
    private function storeAttachment(Message $message, UploadedFile $file): MessageAttachment
    {
        $path = $file->store(self::ATTACHMENT_FOLDER . '/' . date('Y/m'), self::DISK);

        return $message->attachments()->create([
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }

    /**
     * Sunting isi pesan (PRD §15).
     *
     * `edited_at` diisi supaya penerima dapat melihat bahwa pesan pernah
     * berubah — menyunting diam-diam akan membuat riwayat percakapan tidak
     * lagi dapat dipercaya sebagai catatan kerja.
     */
    public function editMessage(Message $message, string $body): Message
    {
        $trimmed = trim($body);

        if ($trimmed === '') {
            throw ValidationException::withMessages([
                'body' => 'Isi pesan tidak boleh kosong.',
            ]);
        }

        $message->body = $trimmed;
        $message->edited_at = now();
        $message->save();

        return $message->refresh()->load(['user', 'attachments', 'parent.user'])->loadCount('replies');
    }

    /**
     * Hapus pesan (soft delete; PRD §15).
     *
     * Soft delete, bukan hapus permanen: balasan yang menunjuk pesan ini tetap
     * punya induk yang valid, dan jejak audit percakapan tidak hilang.
     */
    public function deleteMessage(Message $message): void
    {
        $message->delete();
    }

    /**
     * Sematkan / lepas sematan pesan (PRD §15).
     *
     * Mengembalikan keadaan akhir: true = kini tersemat.
     */
    public function togglePinned(Message $message): bool
    {
        $message->pinned_at = $message->pinned_at === null ? now() : null;
        $message->save();

        return $message->pinned_at !== null;
    }

    /**
     * Cari pesan di dalam SATU percakapan (PRD §15 — pencarian pesan).
     *
     * Pencarian dibatasi pada percakapan yang sedang dibuka, bukan seluruh
     * percakapan pengguna: kotak pencarian ada di halaman percakapan, dan
     * hasil lintas percakapan akan membocorkan judul percakapan lain.
     */
    public function searchMessages(Conversation $conversation, string $term, int $limit = 50): Collection
    {
        $clean = $this->sanitizeSearchTerm($term);

        if ($clean === '') {
            return collect();
        }

        return Message::where('conversation_id', $conversation->getKey())
            ->where('body', 'like', '%' . $clean . '%')
            ->with(['user', 'attachments', 'parent.user'])->withCount('replies')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * Balasan-balasan sebuah pesan (utas), dibatasi jumlahnya.
     *
     * Induknya ikut dimuat supaya panel utas dapat menampilkan konteks pesan
     * yang sedang dibalas.
     */
    public function threadReplies(Message $parent, int $limit = 20): Collection
    {
        return Message::where('parent_id', $parent->getKey())
            ->with(['user', 'attachments', 'parent.user'])->withCount('replies')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /** Pesan yang disematkan pada sebuah percakapan. */
    public function pinnedMessages(Conversation $conversation): Collection
    {
        return Message::where('conversation_id', $conversation->getKey())
            ->pinned()
            ->with('user')
            ->get();
    }

    /** Ambil berkas lampiran dari disk privat (URL `storage/...` sengaja TIDAK pernah dibuat untuk chat). */
    public function attachmentPath(MessageAttachment $attachment): string
    {
        abort_unless(
            Storage::disk(self::DISK)->exists($attachment->path),
            404,
            'Lampiran tidak ditemukan.',
        );

        return $attachment->path;
    }

    /* ------------------------------------------------------------------ */
    /* Tandai sudah dibaca (PRD §9 + rencana B1)                           */
    /* ------------------------------------------------------------------ */

    /**
     * Tandai percakapan sudah dibaca sampai sekarang (PRD §9: saat DIBUKA,
     * bukan saat menggulir sampai pesan terakhir).
     *
     * Versi ini menulis tanpa syarat. Dipakai pada saat membuka halaman dan
     * saat mengirim pesan — momen saat status "sudah dibaca" memang harus
     * diperbarui segera. Untuk polling, pakai `markReadIfDue()`.
     */
    public function markRead(Conversation $conversation, User $user): void
    {
        ConversationMember::where('conversation_id', $conversation->getKey())
            ->where('user_id', $user->getKey())
            ->update(['last_read_at' => now()]);
    }

    /**
     * `markRead()` versi hemat (rencana B1).
     *
     * Dipanggil dari polling. Hanya menulis bila:
     * 1. ada pesan baru di poll ini (`$newCount > 0`), atau
     * 2. sudah lewat jeda throttle sejak tulisan terakhir untuk pasangan
     *    (percakapan, pengguna) ini.
     *
     * Alasan dua syarat: pesan baru harus langsung membuat badge hilang,
     * tetapi poll kosong (mayoritas poll) tidak perlu menulis apa pun. Jeda
     * throttle tetap dipakai sebagai pengaman bila hitungan pesan terlewat,
     * sehingga status "sedang membuka percakapan" (yang dipakai notifikasi
     * lewat `viewing_window_seconds`) tetap segar.
     *
     * Mengembalikan true bila update dilakukan — dipakai pengujian.
     */
    public function markReadIfDue(Conversation $conversation, User $user, int $newCount = 0): bool
    {
        if ($newCount > 0) {
            // Pesan baru: tulis segera, tanpa menunggu jeda. Badge harus
            // bersih dan notifikasi harus tahu bahwa percakapan sedang dibuka.
            $this->markRead($conversation, $user);
            $this->rememberMarkRead($conversation, $user);

            return true;
        }

        // Poll kosong: tulis hanya bila jeda throttle sudah terlewati.
        if ($this->markReadThrottleExpired($conversation, $user)) {
            $this->markRead($conversation, $user);
            $this->rememberMarkRead($conversation, $user);

            return true;
        }

        return false;
    }

    /**
     * True bila jeda throttle sudah terlewat sejak tulisan `last_read_at`
     * terakhir untuk pasangan (percakapan, pengguna) ini.
     */
    private function markReadThrottleExpired(Conversation $conversation, User $user): bool
    {
        return Cache::missing(
            $this->markReadCacheKey($conversation, $user)
        );
    }

    /**
     * Catat bahwa `last_read_at` baru saja ditulis, sehingga poll berikutnya
     * dalam jeda throttle tidak menulis lagi.
     */
    private function rememberMarkRead(Conversation $conversation, User $user): void
    {
        Cache::put(
            $this->markReadCacheKey($conversation, $user),
            now()->getTimestamp(),
            now()->addSeconds(self::MARK_READ_THROTTLE_SECONDS)
        );
    }

    /**
     * Kunci cache untuk pasangan (percakapan, pengguna).
     *
     * Per percakapan, bukan per pengguna: pengguna yang membuka percakapan
     * lain tidak boleh membuat percakapan ini menunggu lebih lama dari jeda
     * throttle.
     */
    private function markReadCacheKey(Conversation $conversation, User $user): string
    {
        return 'chat:mark-read:' . $conversation->getKey() . ':' . $user->getKey();
    }

    /* ------------------------------------------------------------------ */
    /* Polling (PRD §9)                                                    */
    /* ------------------------------------------------------------------ */

    /**
     * Pesan yang lebih baru dari sebuah id, untuk polling.
     *
     * Dibatasi jumlahnya agar satu permintaan tidak menarik seluruh riwayat
     * bila pengguna meninggalkan tab lama sekali.
     */
    public function messagesAfter(Conversation $conversation, int $afterId): Collection
    {
        return Message::where('conversation_id', $conversation->getKey())
            ->newerThan($afterId)
            ->with(['user', 'attachments', 'parent.user'])->withCount('replies')
            ->orderBy('id')
            ->limit(100)
            ->get();
    }

    /**
     * Pesan lama SEBELUM sebuah id, untuk memuat pesan yang lebih lama
     * (rencana B3).
     *
     * `recentMessages()` memuat 50 pesan terbaru; pengguna yang ingin membaca
     * pesan lama memanggil metode ini berulang dengan id pesan TERKECIL yang
     * sudah dimuat. Hasilnya tetap dalam urutan kronologis (termuda di
     * akhir), sama seperti `recentMessages()`, sehingga frontend tinggal
     * menempelkannya di ATAS daftar yang sudah ada tanpa memutar urutan.
     *
     * Mengembalikan koleksi kosong bila tidak ada lagi pesan — frontend
     * memakainya untuk menyembunyikan tombol "Muat lebih lama".
     */
    public function messagesBefore(Conversation $conversation, int $beforeId, int $limit = 50): Collection
    {
        return Message::where('conversation_id', $conversation->getKey())
            ->where('id', '<', $beforeId)
            ->with(['user', 'attachments', 'parent.user'])->withCount('replies')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    /** Pesan terbaru untuk memuat awal sebuah percakapan. */
    public function recentMessages(Conversation $conversation, int $limit = 50): Collection
    {
        return Message::where('conversation_id', $conversation->getKey())
            ->with(['user', 'attachments', 'parent.user'])->withCount('replies')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }
}
