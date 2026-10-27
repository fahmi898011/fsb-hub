{{-- AGENT: asisten | 2026-10-10 | markup baris percakapan dipindah ke chat/_conversation_list agar dipakai bersama panel samping | cegah dua salinan markup yang menyimpang --}}
@extends('layouts.app')

@section('title', 'Chat')

@section('content')
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-ink-900">Chat</h1>
            <p class="text-sm text-ink-500">Percakapan pribadi dan kanal yang Anda ikuti.</p>
        </div>

        <div class="flex shrink-0 gap-2">
            <a href="{{ route('chat.directory') }}" class="btn-secondary">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                </svg>
                Percakapan Baru
            </a>

            @can('channel.create')
                <button type="button" class="btn-primary" @click="$dispatch('open-modal', 'channel-create')">
                    Buat Kanal
                </button>
            @endcan
        </div>
    </div>

    <form method="GET" action="{{ route('chat.index') }}" class="card card-body mb-4">
        <label for="search" class="form-label">Cari percakapan</label>
        <div class="flex gap-2">
            <input id="search" name="search" type="search" value="{{ $search }}" class="form-input"
                   placeholder="Nama kanal, nama rekan kerja, atau isi pesan">
            <button type="submit" class="btn-secondary shrink-0">Cari</button>
            @if ($search)
                <a href="{{ route('chat.index') }}" class="btn-ghost shrink-0">Reset</a>
            @endif
        </div>
    </form>

    @if ($conversations->isEmpty())
        <x-empty-state
            title="Belum ada percakapan"
            description="Mulai percakapan pribadi dengan rekan kerja lewat tombol Percakapan Baru."
        >
            <a href="{{ route('chat.directory') }}" class="btn-primary">Lihat Daftar Karyawan</a>
        </x-empty-state>
    @else
        {{-- Markup baris percakapan dipakai bersama halaman ini dan panel
             samping halaman percakapan (chat/_conversation_list.blade.php),
             supaya kedua tempat tidak perlahan menyimpang. --}}
        @include('chat._conversation_list', [
            'conversations' => $conversations,
            'unread' => $unread,
        ])

        <div class="mt-4">
            {{ $conversations->links() }}
        </div>
    @endif

    @can('channel.create')
        <x-modal name="channel-create" maxWidth="lg" focusable>
            <form method="POST" action="{{ route('chat.channels.store') }}" class="p-6">
                @csrf

                <h2 class="text-base font-semibold text-ink-900">Buat Kanal Baru</h2>
                <p class="mt-0.5 text-sm text-ink-500">
                    Anda otomatis menjadi anggota pertama. Anggota lain ditambahkan secara manual.
                </p>

                <div class="mt-5 space-y-4">
                    <div>
                        <label for="channel-name" class="form-label">Nama kanal</label>
                        <input id="channel-name" name="name" type="text" value="{{ old('name') }}" required maxlength="100" class="form-input">
                        @error('name')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="channel-description" class="form-label">Deskripsi <span class="text-ink-400">(opsional)</span></label>
                        <textarea id="channel-description" name="description" rows="2" maxlength="500" class="form-input">{{ old('description') }}</textarea>
                    </div>

                    <label class="flex items-start gap-2.5 rounded-xl border border-ink-200 p-3">
                        <input type="checkbox" name="is_private" value="1" class="mt-0.5 rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                        <span class="text-sm">
                            <span class="block font-medium text-ink-800">Kanal privat</span>
                            <span class="block text-ink-500">Hanya anggota yang ditambahkan dapat membukanya.</span>
                        </span>
                    </label>

                    @if ($users->isNotEmpty())
                        <div>
                            <span class="form-label">Anggota awal <span class="text-ink-400">(opsional)</span></span>
                            <div class="max-h-56 space-y-1 overflow-y-auto rounded-xl border border-ink-200 p-2">
                                @foreach ($users as $user)
                                    <label class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 hover:bg-ink-50">
                                        <input type="checkbox" name="members[]" value="{{ $user->id }}"
                                               class="rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                                        <span class="text-sm text-ink-700">{{ $user->name }}</span>
                                        <span class="ml-auto text-xs text-ink-400">{{ $user->departmentName() }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="btn-secondary" @click="$dispatch('close-modal', 'channel-create')">Batal</button>
                    <button type="submit" class="btn-primary">Buat Kanal</button>
                </div>
            </form>
        </x-modal>
    @endcan
@endsection

@push('scripts')
    <script>
        // Polling daftar percakapan (PRD Chat §9, target ketiga).
        //
        // Sebelum ini daftar hanya dirender sekali saat halaman dibuka, jadi
        // percakapan yang baru saja mendapat pesan tidak berubah sampai halaman
        // dimuat ulang. Endpoint mengembalikan potongan HTML yang SAMA dengan
        // yang dirender server, sehingga menukar satu simpul sudah cukup dan
        // tampilannya tidak mungkin menyimpang dari versi server.
        //
        // `window.location.search` diteruskan apa adanya supaya yang disegarkan
        // adalah tampilan yang SEDANG dilihat (kata kunci pencarian dan nomor
        // halaman), bukan selalu halaman pertama.
        //
        // Jeda & backoff diserahkan ke `window.fsbPollLoop`
        // (layouts/partials/poll_loop.blade.php): tetap cepat selama daftar
        // berubah, melambat sendiri saat halaman menganggur, dan langsung
        // menyegarkan saat tab/jendela kembali aktif ATAU saat badge
        // belum-dibaca berubah (peristiwa `fsb:chat-unread-changed`).
        (() => {
            // Pengaturan dibaca dari `window.fsbPollSettings` (lihat
            // layouts/partials/poll_loop.blade.php) supaya angkanya hanya ada di
            // config/chat.php. Angka cadangan di sini hanya jaring pengaman bila
            // partial itu suatu saat tidak ikut dirender.
            const settings = window.fsbPollSettings || {};
            const interval = Number(settings.conversations ?? 15000);
            const idleAfter = Number(settings.idleAfter ?? 6);
            const idleMultiplier = Number(settings.idleMultiplier ?? 4);

            // Tanpa penjadwal bersama, polling dimatikan saja: daftar tetap
            // benar saat halaman dimuat, jadi yang hilang hanya pembaruan
            // langsung — jauh lebih baik daripada skrip yang melempar.
            if (typeof window.fsbPollLoop !== 'function') return;

            const url = @json(route('chat.conversations.poll'));

            // Menukar daftar tepat saat tombol mouse masih ditekan membuat klik
            // pengguna mendarat di elemen yang sudah dibuang (kliknya diam-diam
            // gagal). Selama tombol masih ditekan, penukaran ditunda — lewat
            // `canRun` di penjadwal, supaya siklusnya pun tidak dijalankan.
            let pointerDown = false;

            document.addEventListener('pointerdown', () => { pointerDown = true; });
            document.addEventListener('pointerup', () => { pointerDown = false; });
            document.addEventListener('pointercancel', () => { pointerDown = false; });

            // Markup "stabil": label waktu relatif ("2 menit lalu") berubah
            // sendiri mengikuti jam, jadi saat memutuskan perlu-tukar atau tidak
            // label itu diganti waktu tetapnya (`data-chat-time`). Tanpa ini
            // daftar dianggap berubah SETIAP siklus dan backoff tidak pernah
            // aktif.
            const stable = (node) => {
                const clone = node.cloneNode(true);

                clone.querySelectorAll('[data-chat-time]').forEach((el) => {
                    el.textContent = el.dataset.chatTime;
                });

                return clone.innerHTML;
            };

            const tick = async () => {
                const target = document.querySelector('[data-chat-conversation-list]');

                // Halaman daftar yang kosong tidak punya simpul ini; tidak ada
                // yang bisa ditukar.
                if (!target) return false;

                const response = await fetch(url + window.location.search, {
                    headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });

                if (!response.ok) return false;

                const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
                const fresh = doc.querySelector('[data-chat-conversation-list]');

                // Daftar kosong tidak ditukar: keadaan "belum ada percakapan"
                // dirender sebagai empty-state di LUAR simpul ini, jadi
                // menukarnya dengan daftar kosong justru hanya menghapus
                // tampilan itu. Transisi kosong -> ada isi karenanya menunggu
                // muat ulang; badge sidebar tetap menyala lebih dulu sebagai
                // penanda.
                if (!fresh || fresh.children.length === 0) return false;

                // Hanya tukar bila isinya benar-benar BERBEDA. Menukar simpul
                // yang sama membuang posisi gulir dan seleksi teks pengguna
                // tanpa manfaat apa pun — dan membuat jeda tidak pernah melambat
                // karena seolah selalu ada yang berubah.
                if (stable(fresh) === stable(target)) {
                    // Bedanya hanya label waktu: perbarui di tempat supaya
                    // simpulnya tidak perlu dibuang (posisi gulir, sorotan
                    // hover, dan seleksi teks pengguna tetap utuh).
                    const freshTimes = fresh.querySelectorAll('[data-chat-time]');

                    target.querySelectorAll('[data-chat-time]').forEach((node, index) => {
                        const text = freshTimes[index]?.textContent;

                        if (text !== undefined && node.textContent !== text) {
                            node.textContent = text;
                        }
                    });

                    return false;
                }

                target.replaceWith(fresh);

                return true;
            };

            const loop = window.fsbPollLoop({
                interval,
                idleAfter,
                idleMultiplier,
                canRun: () => !pointerDown,
                tick,
            });

            // Badge belum-dibaca berubah = ada pesan baru untuk pengguna. Daftar
            // langsung disegarkan alih-alih menunggu siklusnya sendiri, dan
            // jedanya kembali ke tingkat cepat.
            document.addEventListener('fsb:chat-unread-changed', () => loop.kick());

            loop.start();
        })();
    </script>
@endpush
