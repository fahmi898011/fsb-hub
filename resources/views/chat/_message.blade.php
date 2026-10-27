{{--
    Satu gelembung pesan. Dipakai tiga tempat: render awal halaman
    percakapan, balasan HTML dari endpoint polling, dan draf "Mengirim..."
    yang disisipkan JavaScript sebelum server merespons — sehingga
    tampilan pesan yang baru masuk tidak bisa berbeda dari pesan lama
    (satu partial, satu sumber kebenaran).

    Variabel:
      $message         (wajib) pesan yang dirender.
      $previousMessage (opsional) pesan sebelumnya dalam urutan yang sama;
                       dipakai mengelompokkan pesan berurutan dari pengirim
                       yang sama (A8) dan memisahkan hari (A9).
      $isDraft         (opsional) true untuk draf optimistis (A3).
      $conversation    (opsional) dipakai membentuk URL aksi & utas.
      $suppressSeparator (opsional) true untuk mematikan pemisah hari; dipakai
                       saat partial ini dirender SENDIRIAN untuk mengganti satu
                       pesan di tempat (hasil sunting / hasil pencarian), yang
                       tidak boleh menyisipkan pemisah hari ke tengah riwayat.

    AGENT: asisten | 2026-10-06 | tambah aksi pesan (sunting/hapus/semat/balas) | modul Chat §15
--}}

@php($isMine = ($isDraft ?? false) || $message->user_id === auth()->id())
@php($createdAt = $message->created_at ?? \Illuminate\Support\Carbon::now())
@php($previous = $previousMessage ?? null)
@php($continued = $previous !== null
    && $previous->user_id === $message->user_id
    && $previous->created_at?->isSameDay($createdAt))
@php($showSender = ! $isMine && ! $continued)
@php($dateKey = $createdAt->format('Y-m-d'))
@php($dayLabel = $createdAt->isToday()
    ? 'Hari ini'
    : ($createdAt->isYesterday() ? 'Kemarin' : $createdAt->format('d M Y')))

{{-- `$isDraft` tidak pernah berupa model tersimpan, jadi semua aksi
     (yang memerlukan id) hanya dirender untuk pesan nyata. --}}
@php($isReal = ! ($isDraft ?? false) && $message->exists)

@if ($isReal && ! ($suppressSeparator ?? false) && (! $previous || ! $previous->created_at?->isSameDay($createdAt)))
    <div class="chat-day-separator" data-date="{{ $dateKey }}">
        <span>{{ $dayLabel }}</span>
    </div>
@endif

<div class="chat-message {{ $isMine ? 'chat-message--mine' : 'chat-message--them' }}{{ $continued ? ' chat-message--continued' : '' }}{{ ($isDraft ?? false) ? ' chat-message--draft' : '' }}"
     data-message-id="{{ $message->id ?? '' }}"
     data-sender-id="{{ $message->user_id }}"
     data-date="{{ $dateKey }}"
     @if ($isReal) data-chat-message data-edited="{{ $message->isEdited() ? '1' : '0' }}"
        data-pinned="{{ $message->isPinned() ? '1' : '0' }}"
        data-author="{{ $message->user?->name ?? 'Pengguna dihapus' }}" @endif
     @if ($isDraft ?? false) data-pending="1" @endif>
    @if (! $isMine)
        <div class="chat-avatar">
            @if ($showSender)
                <img src="{{ $message->user?->avatar_url }}" alt="" class="h-8 w-8 rounded-full object-cover ring-1 ring-ink-200">
            @endif
        </div>
    @endif

    <div class="chat-bubble-col {{ $isMine ? 'chat-bubble-col--mine' : '' }}">
        @if ($showSender)
            <p class="chat-sender">{{ $message->user?->name ?? 'Pengguna dihapus' }}</p>
        @endif

        {{-- Kutipan pesan induk bila ini sebuah balasan (PRD §15 — reply/thread). --}}
        @if ($isReal && $message->parent)
            <button type="button" class="chat-reply-quote" data-chat-open-thread="{{ $message->parent_id }}"
                    title="Lihat utas">
                <span class="chat-reply-quote-author">{{ $message->parent->user?->name ?? 'Pengguna dihapus' }}</span>
                <span class="chat-reply-quote-body">{{ \Illuminate\Support\Str::limit($message->parent->body, 120) }}</span>
            </button>
        @endif

        <div class="chat-bubble {{ $isMine ? 'chat-bubble-mine' : 'chat-bubble-them' }}">
            @if ($message->body !== '' && $message->body !== null)
                <p class="chat-bubble-body" data-draft-body data-chat-body>{{ $message->body }}</p>
            @endif

            @if (! ($isDraft ?? false) && $message->relationLoaded('attachments') && $message->attachments->isNotEmpty())
                <div class="chat-attachments">
                    @foreach ($message->attachments as $attachment)
                        @if ($attachment->isImage())
                            <a href="{{ route('chat.attachments.show', $attachment) }}" target="_blank" rel="noopener"
                               class="chat-attachment-image">
                                <img src="{{ route('chat.attachments.show', ['attachment' => $attachment, 'inline' => 1]) }}"
                                     alt="{{ $attachment->original_name }}" class="h-32 w-32 object-cover">
                            </a>
                        @else
                            <a href="{{ route('chat.attachments.show', $attachment) }}" class="chat-attachment-file">
                                <svg class="h-4 w-4 shrink-0 text-ink-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 3v5h5M7 3h7l5 5v11a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" />
                                </svg>
                                <span class="chat-attachment-name">{{ $attachment->original_name }}</span>
                                <span class="chat-attachment-size">{{ $attachment->human_size }}</span>
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        @if ($isDraft ?? false)
            <p class="chat-draft-status">Mengirim...</p>
        @else
            @php($fullTimestamp = $createdAt->format('d M Y H:i'))
            @php($shortTimestamp = $createdAt->gt(\Illuminate\Support\Carbon::now()->subDay())
                ? $createdAt->locale('id')->diffForHumans()
                : $fullTimestamp)
            @php($replyCount = (int) ($message->replies_count ?? 0))
            <div class="chat-meta">
                <time class="chat-time" datetime="{{ $createdAt->toIso8601String() }}" title="{{ $fullTimestamp }}">{{ $shortTimestamp }}</time>

                @if ($message->isEdited())
                    <span class="chat-flag" title="Pesan pernah disunting">disunting</span>
                @endif

                @if ($message->isPinned())
                    <span class="chat-flag chat-flag--pinned" title="Pesan disematkan">
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 3l6 6-3 1-4 4-1 4-3-3-4 4-1-1 4-4-3-3 4-1 4-4 1-3Z" />
                        </svg>
                        disematkan
                    </span>
                @endif

                @if ($isReal && $replyCount > 0)
                    <button type="button" class="chat-thread-link" data-chat-open-thread="{{ $message->id }}">
                        {{ $replyCount }} balasan
                    </button>
                @endif
            </div>

            {{-- Aksi pesan (PRD §15). Dirender hanya bila pengguna memang
                 berhak, supaya tidak ada tombol yang pasti gagal saat diklik.
                 Tombol yang tampil pun tetap diverifikasi policy di server. --}}
            @if ($isReal && isset($conversation))
                @php($canEdit = auth()->user()->can('update', $message))
                @php($canDelete = auth()->user()->can('delete', $message))
                @php($canPin = auth()->user()->can('pin', $message))
                <div class="chat-actions" data-chat-actions>
                    <button type="button" class="chat-action" data-chat-action="reply"
                            data-url="{{ route('chat.messages.thread', [$conversation, $message]) }}"
                            title="Balas" aria-label="Balas pesan">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 14l-5-5 5-5M4 9h10a6 6 0 0 1 6 6v4" />
                        </svg>
                    </button>

                    @if ($canPin)
                        <button type="button" class="chat-action{{ $message->isPinned() ? ' chat-action--active' : '' }}"
                                data-chat-action="pin"
                                data-url="{{ route('chat.messages.pin', [$conversation, $message]) }}"
                                data-pinned="{{ $message->isPinned() ? '1' : '0' }}"
                                title="{{ $message->isPinned() ? 'Lepas sematan' : 'Sematkan' }}"
                                aria-label="{{ $message->isPinned() ? 'Lepas sematan' : 'Sematkan pesan' }}">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 3l6 6-3 1-4 4-1 4-3-3-4 4-1-1 4-4-3-3 4-1 4-4 1-3Z" />
                            </svg>
                        </button>
                    @endif

                    @if ($canEdit)
                        <button type="button" class="chat-action" data-chat-action="edit"
                                data-url="{{ route('chat.messages.update', [$conversation, $message]) }}"
                                title="Sunting" aria-label="Sunting pesan">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 20h4l10-10-4-4L4 16v4ZM14 6l4 4" />
                            </svg>
                        </button>
                    @endif

                    @if ($canDelete)
                        <button type="button" class="chat-action chat-action--danger" data-chat-action="delete"
                                data-url="{{ route('chat.messages.destroy', [$conversation, $message]) }}"
                                title="Hapus" aria-label="Hapus pesan">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V5h6v2M8 7l1 12h6l1-12" />
                            </svg>
                        </button>
                    @endif
                </div>
            @endif
        @endif
    </div>
</div>
