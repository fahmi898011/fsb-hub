{{-- AGENT: asisten | 2026-10-10 | partial baru: markup baris percakapan dipakai bersama index + panel samping | hindari dua salinan markup yang menyimpang --}}
{{-- AGENT: asisten | 2026-10-10 | tandai label waktu relatif dengan data-chat-time (dipakai poller daftar) | modul Chat §9 --}}
{{--
    Daftar percakapan yang dapat dipakai ulang.

    Dipakai dua tempat: halaman daftar penuh (chat/index.blade.php) dan
    panel samping di halaman percakapan (chat/show.blade.php). Menyimpannya
    di satu partial mencegah dua salinan markup yang perlahan menyimpang —
    bug paling umum pada daftar yang dirender di lebih dari satu tempat.

    Variabel:
    - $conversations : LengthAwarePaginator percakapan
    - $unread        : array<int,int> jumlah belum dibaca per id
    - $activeId      : (opsional) id percakapan yang sedang dibuka
    - $compact       : (opsional) true = versi ringkas untuk panel samping

    Catatan: baris TIDAK memakai <a href> penuh pada versi panel samping.
    Navigasi antar percakapan ditangani JavaScript (pushState), jadi
    tautannya tetap ada sebagai cadangan bila JS mati — bukan dihapus.
--}}
@php($compact = $compact ?? false)
@php($activeId = $activeId ?? null)

<div class="{{ $compact ? 'divide-y divide-ink-100' : 'card divide-y divide-ink-100' }}" data-chat-conversation-list>
    @foreach ($conversations as $item)
        @php($unreadTotal = $unread[$item->id] ?? 0)
        @php($last = $item->latestMessage)
        @php($isActive = $activeId !== null && (int) $activeId === (int) $item->id)

        <a href="{{ route('chat.show', $item) }}"
           data-chat-conversation-link="{{ $item->id }}"
           @if ($isActive) aria-current="true" @endif
           class="flex items-start gap-3 px-4 py-3 transition-colors hover:bg-ink-50/60 {{ $isActive ? 'bg-brand-50/60' : ($unreadTotal > 0 ? 'bg-brand-50/30' : '') }}">
            @if ($item->isDirect())
                <img src="{{ $item->otherParticipant(auth()->user())?->avatar_url }}" alt=""
                     class="mt-0.5 h-9 w-9 shrink-0 rounded-full object-cover ring-1 ring-ink-200">
            @else
                <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-ink-100 text-ink-500">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h6m-9 8V6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H7l-3 3Z" />
                    </svg>
                </span>
            @endif

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="truncate text-sm font-semibold text-ink-900">{{ $item->titleFor(auth()->user()) }}</p>

                    @if ($item->isChannel())
                        <x-badge :variant="$item->is_private ? 'amber' : 'gray'">
                            {{ $item->is_private ? 'Privat' : 'Kanal' }}
                        </x-badge>
                    @endif

                    @if ($unreadTotal > 0)
                        {{-- Ditandai agar JS dapat menghapus badge ini saat
                             percakapan dibuka lewat pushState (server sudah
                             menandainya dibaca, tetapi panel tidak ditukar). --}}
                        <x-badge variant="brand" data-chat-unread>{{ $unreadTotal }} baru</x-badge>
                    @endif
                </div>

                <p class="mt-0.5 truncate text-sm text-ink-500">
                    @if ($last)
                        <span class="font-medium text-ink-600">{{ $last->user?->name ?? 'Pengguna dihapus' }}:</span>
                        {{ \Illuminate\Support\Str::limit($last->body ?: 'Lampiran', 80) }}
                    @else
                        Belum ada pesan.
                    @endif
                </p>
            </div>

            @if ($last)
                {{-- Waktu TETAP-nya ikut ditandai: poller daftar membandingkan
                     markup dengan label relatif ini diganti waktu tetap, supaya
                     "2 menit lalu" yang berubah sendiri tidak dianggap sebagai
                     perubahan dan tidak memicu penukaran simpul. --}}
                <span class="shrink-0 text-xs text-ink-400"
                      data-chat-time="{{ $last->created_at?->toIso8601String() }}">{{ $last->created_at?->diffForHumans() }}</span>
            @endif
        </a>
    @endforeach
</div>
