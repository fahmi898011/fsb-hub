<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pemicu notifikasi modul Chat (PRD Chat §11).
 *
 * Dipisah dari NotificationService dengan alasan yang sama seperti
 * InformationNotifier/TicketNotifier: audiens, kalimat, dan tautan terpusat di
 * satu tempat.
 *
 * Aturan agregat (tiga-tiganya dari PRD §11):
 * 1. Pengirim pesan tidak menerima notifikasi pesannya sendiri.
 * 2. Anggota yang SEDANG membuka percakapan itu tidak diberi tahu.
 * 3. Pesan beruntun dalam percakapan yang sama digabung menjadi SATU
 *    notifikasi ("3 pesan baru di Divisi QA"), bukan satu notifikasi per pesan.
 *
 * Nomor 3 diwujudkan tanpa kolom tambahan: hitungan TIDAK disimpan di baris
 * notifikasi, melainkan dihitung ulang dari `last_read_at` anggota. Dengan
 * begitu tidak ada angka basi yang bisa menyimpang dari kenyataan pesan.
 *
 * AGENT: asisten | 2026-10-06 | tambah mentions() (sebutan @) | modul Chat §15
 */
class ChatNotifier
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * Dipanggil setelah sebuah pesan tersimpan.
     *
     * @param  array<int, User>  $recipients  anggota selain pengirim
     */
    public function newMessage(Conversation $conversation, Message $message, User $sender, array $recipients): void
    {
        if ($recipients === []) {
            return;
        }

        $viewingWindow = now()->subSeconds((int) config('chat.viewing_window_seconds', 15));
        $url = route('chat.show', $conversation);
        $title = $conversation->isDirect() ? 'Pesan baru' : 'Pesan baru di ' . $conversation->name;

        foreach ($recipients as $recipient) {
            if (! $recipient->isActive() || $recipient->is($sender)) {
                continue;
            }

            // Aturan 2: sedang membuka percakapan -> tidak perlu diberi tahu.
            $lastRead = $this->lastReadAt($conversation, $recipient);

            if ($lastRead && $lastRead->greaterThan($viewingWindow)) {
                continue;
            }

            // Aturan 3: hitung pesan yang belum dibaca penerima.
            $unread = $this->unreadCount($conversation, $recipient);

            $existing = AppNotification::where('user_id', $recipient->getKey())
                ->where('type', 'chat')
                ->where('url', $url)
                ->unread()
                ->latest()
                ->first();

            $body = $this->body($conversation, $sender, $unread);

            if ($existing) {
                // Gabungkan: cukup perbarui isinya, jangan tambah baris baru.
                $existing->update([
                    'title' => $title,
                    'message' => $body,
                    'created_at' => now(),
                ]);

                continue;
            }

            $this->notifications->notify($recipient, $title, $body, 'chat', $url);
        }
    }

    /**
     * Notifikasi sebutan `@` (PRD §15 — mention).
     *
     * Dipisah dari `newMessage()` karena aturannya berbeda dan lebih kuat:
     *
     * 1. Penerima yang disebut SELALU diberi tahu, bahkan bila ia sedang
     *    membuka percakapan itu. Sebutan adalah permintaan perhatian langsung;
     *    menelannya diam-diam karena kebetulan tabnya terbuka akan membuat
     *    fitur ini tidak berguna.
     * 2. Notifikasi sebutan TIDAK digabung dengan notifikasi "pesan baru".
     *    Menggabungkannya akan mengubah "Anda disebut" menjadi "5 pesan baru",
     *    dan justru itulah informasi yang paling penting bagi penerimanya.
     *
     * Hanya anggota percakapan yang dapat disebut — nama orang di luar
     * percakapan diabaikan, supaya sebutan tidak menjadi celah menyapa
     * orang yang tidak punya akses ke percakapan itu.
     */
    public function mentions(Conversation $conversation, Message $message, User $sender, array $members): void
    {
        $names = $message->mentionedNames();

        if ($names === []) {
            return;
        }

        $url = route('chat.show', $conversation);
        $title = $conversation->isDirect()
            ? 'Anda disebut'
            : 'Anda disebut di ' . $conversation->name;

        // Bandingkan tanpa peka huruf besar-kecil, dan terima baik nama
        // lengkap ("Budi Santoso") maupun potongan sebelum spasi ("Budi"),
        // karena mengetik nama lengkap bertitik jauh lebih merepotkan
        // daripada mengetik nama panggilan.
        $lookup = array_map(fn (string $name) => mb_strtolower($name), $names);

        foreach ($members as $member) {
            if (! $member->isActive() || $member->is($sender)) {
                continue;
            }

            if (! $this->isMentioned($member, $lookup)) {
                continue;
            }

            $body = $sender->name . ' menyebut Anda: ' . $this->excerpt($message->body);

            $this->notifications->notify($member, $title, $body, 'chat', $url);
        }
    }

    /** Benar bila nama user cocok dengan salah satu token sebutan. */
    private function isMentioned(User $user, array $lookup): bool
    {
        $full = mb_strtolower((string) $user->name);

        if ($full === '') {
            return false;
        }

        $first = explode(' ', $full)[0];

        return in_array($full, $lookup, true) || in_array($first, $lookup, true);
    }

    /** Potongan badan pesan untuk isi notifikasi, agar tetap satu baris. */
    private function excerpt(?string $body): string
    {
        $flat = trim(preg_replace('/\s+/u', ' ', (string) $body));

        return mb_strimwidth($flat, 0, 120, '…');
    }

    /**
     * Jumlah pesan yang belum dibaca seorang anggota.
     *
     * Pesan kiriman sendiri tidak dihitung: mengirim pesan sudah menaikkan
     * `last_read_at` pengirim, tetapi pengecualian ini membuat hitungannya
     * benar walaupun urutan operasinya berubah.
     *
     * Dihitung langsung dari `created_at` versus `last_read_at` — tidak ada
     * angka yang disimpan, sehingga tidak mungkin basi.
     */
    public function unreadCount(Conversation $conversation, User $user): int
    {
        return (int) DB::table('messages')
            ->where('messages.conversation_id', $conversation->getKey())
            ->whereNull('messages.deleted_at')
            ->where('messages.user_id', '!=', $user->getKey())
            ->where(function ($query) use ($conversation, $user) {
                $query->whereNull('conversation_members.last_read_at')
                    ->orWhereColumn('messages.created_at', '>', 'conversation_members.last_read_at');
            })
            ->join('conversation_members', function ($join) use ($conversation, $user) {
                $join->on('conversation_members.conversation_id', '=', 'messages.conversation_id')
                    ->where('conversation_members.conversation_id', '=', $conversation->getKey())
                    ->where('conversation_members.user_id', '=', $user->getKey());
            })
            ->count();
    }

    private function lastReadAt(Conversation $conversation, User $user): ?Carbon
    {
        $value = DB::table('conversation_members')
            ->where('conversation_id', $conversation->getKey())
            ->where('user_id', $user->getKey())
            ->value('last_read_at');

        return $value ? Carbon::parse($value) : null;
    }

    private function body(Conversation $conversation, User $sender, int $unread): string
    {
        if ($conversation->isDirect()) {
            return $unread > 1
                ? $sender->name . ' mengirim ' . $unread . ' pesan baru.'
                : $sender->name . ' mengirim pesan baru.';
        }

        return $unread > 1
            ? $unread . ' pesan baru di ' . $conversation->name . '.'
            : $sender->name . ' mengirim pesan di ' . $conversation->name . '.';
    }
}
