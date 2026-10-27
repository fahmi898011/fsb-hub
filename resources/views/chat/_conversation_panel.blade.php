{{-- AGENT: asisten | 2026-10-10 | panel samping daftar percakapan di halaman chat.show | pindah chat cepat tanpa memuat ulang --}}
{{--
    Panel samping daftar percakapan (halaman percakapan).
{{--
    Panel samping daftar percakapan (halaman percakapan).

    Berisi pencarian ringan + daftar percakapan. Tautan percakapan di sini
    ditangani JavaScript (pushState) supaya berpindah chat tidak memuat
    ulang halaman; `href` tetap diisi sebagai cadangan bila JS mati.

    Daftar ini TIDAK ikut ditukar bersama area percakapan: posisi gulirnya
    harus bertahan saat pengguna berpindah-pindah chat. Karena itu panel
    berada di luar `[data-chat-main]`.
--}}
@php($activeId = $activeId ?? null)

<div class="card flex max-h-[75vh] flex-col overflow-hidden">
    <div class="border-b border-ink-100 p-3">
        <form method="GET" action="{{ route('chat.index') }}">
            <label for="panel-search" class="sr-only">Cari percakapan</label>
            <input id="panel-search" name="search" type="search" class="form-input"
                   placeholder="Cari percakapan..." autocomplete="off">
        </form>
    </div>

    <div class="chat-scroll min-h-0 flex-1 overflow-y-auto">
        @if ($conversations->isEmpty())
            <p class="px-4 py-6 text-center text-sm text-ink-500">Belum ada percakapan.</p>
        @else
            @include('chat._conversation_list', [
                'conversations' => $conversations,
                'unread' => $unread,
                'activeId' => $activeId,
                'compact' => true,
            ])
        @endif
    </div>

    <div class="border-t border-ink-100 p-3">
        <a href="{{ route('chat.directory') }}" class="btn-secondary w-full justify-center">
            Percakapan Baru
        </a>
    </div>
</div>
