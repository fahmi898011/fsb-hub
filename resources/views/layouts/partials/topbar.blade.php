<header class="app-topbar">
    {{-- AGENT: asisten | 2026-10-06 | rounded-xl untuk Tema A | UI/UX --}}
    {{-- Buka menu (mobile) --}}
    <button type="button" @click="sidebarOpen = true" class="btn-icon lg:hidden" aria-label="Buka menu">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
    </button>

    {{-- Lipat sidebar (desktop) --}}
    <button type="button" @click="collapsed = !collapsed" class="btn-icon hidden lg:inline-flex" aria-label="Lipat menu">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
    </button>

    <div class="min-w-0">
        <h1 class="truncate text-base font-semibold text-ink-900 sm:text-lg">@yield('title', $title ?? 'Beranda')</h1>
    </div>

    <div class="ml-auto flex items-center gap-2">
        {{-- Pencarian ringkas (desktop) — lintas modul, debounce 300 ms. --}}
        {{-- AGENT: asisten | 2026-10-16 | tambah hasil lintas modul | modul Pencarian --}}
        {{-- Beranda sudah punya kotak pencarian besar di header. Dua kotak
             pencarian pada satu layar membingungkan, dan dua handler ⌘K akan
             saling menimpa fokus. --}}
        @unless (request()->routeIs('home'))
        <div class="relative hidden md:block" x-data="liveSearch()" @click.outside="close()">
            <form action="{{ route('search') }}" method="GET" @submit="open = false">
                <label class="relative block">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-ink-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.3-4.3M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14Z" />
                        </svg>
                    </span>
                    <input
                        type="search"
                        name="q"
                        x-model="term"
                        @input.debounce.300ms="fetchResults()"
                        @focus="open = term.trim().length >= 3"
                        @keydown.escape="close()"
                        value="{{ request('q') }}"
                        placeholder="Cari di semua modul..."
                        autocomplete="off"
                        aria-label="Cari di semua modul"
                        class="form-input w-56 py-2 pl-9 lg:w-64"
                    >
                </label>
            </form>

            {{-- Hasil dimuat dari /cari/langsung. Setiap modul disaring di
                 server menurut hak pengguna, jadi dropdown tidak pernah
                 menampilkan catatan/chat/tiket orang lain. --}}
            <div
                x-show="open"
                x-cloak
                x-transition.origin.top.right
                class="absolute right-0 z-50 mt-2 w-96 overflow-hidden rounded-xl border border-ink-200 bg-white shadow-card-hover"
            >
                <template x-if="loading">
                    <p class="px-4 py-3 text-sm text-ink-500">Mencari…</p>
                </template>

                <template x-if="! loading && groups.length === 0">
                    <p class="px-4 py-3 text-sm text-ink-500" x-text="hint || 'Tidak ada hasil.'"></p>
                </template>

                <div class="max-h-96 overflow-y-auto">
                    <template x-for="group in groups" :key="group.module">
                        <div class="border-b border-ink-100 last:border-b-0">
                            <p class="px-4 pb-1 pt-3 text-[11px] font-semibold uppercase tracking-wide text-ink-400"
                               x-text="group.module"></p>
                            <template x-for="item in group.items" :key="item.url + item.title">
                                <a :href="item.url" class="block px-4 py-2 transition-colors hover:bg-ink-50">
                                    <p class="truncate text-sm font-medium text-ink-800" x-text="item.title"></p>
                                    <p class="truncate text-xs text-ink-500" x-text="item.subtitle"></p>
                                </a>
                            </template>
                        </div>
                    </template>
                </div>

                <a :href="moreUrl"
                   class="block border-t border-ink-100 bg-ink-50/60 px-4 py-2.5 text-center text-xs font-medium text-brand-700 hover:bg-ink-50">
                    Lihat semua hasil
                </a>
            </div>
        </div>
        @endunless

        {{-- Notifikasi --}}
        <a href="{{ route('notifications.index') }}" class="btn-icon relative" aria-label="Notifikasi">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h11Zm0 0a3 3 0 0 1-6 0" />
            </svg>
            {{-- Badge SELALU dirender (disembunyikan saat nol), bukan dibungkus
                 `@if`: skrip polling badge di layout memperbaruinya tanpa
                 memuat ulang halaman (PRD Chat §9), jadi elemennya harus ada
                 sejak awal. Kelas `hidden` ditambah/dibuang oleh JS. --}}
            <span data-notif-badge data-badge-cap="9"
                  class="{{ ($unreadNotificationCount ?? 0) > 0 ? '' : 'hidden' }} absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold leading-none text-white ring-2 ring-white">
                {{ $unreadNotificationCount > 9 ? '9+' : $unreadNotificationCount }}
            </span>
        </a>

        {{-- Profil --}}
        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
            <button type="button" @click="open = ! open" class="flex items-center gap-2 rounded-xl p-1 pr-2 transition-colors hover:bg-ink-100">
                <img src="{{ auth()->user()->avatar_url }}" alt="" class="h-9 w-9 rounded-full object-cover ring-2 ring-white shadow-sm">
                <div class="hidden text-left sm:block">
                    <p class="max-w-[10rem] truncate text-sm font-semibold leading-tight text-ink-800">{{ auth()->user()->name }}</p>
                    <p class="text-xs leading-tight text-ink-500">{{ auth()->user()->role_label }}</p>
                </div>
                <svg class="hidden h-4 w-4 text-ink-400 sm:block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                </svg>
            </button>

            <div x-show="open" x-transition.origin.top.right x-cloak class="absolute right-0 mt-2 w-52 overflow-hidden rounded-xl border border-ink-200 bg-white py-1.5 shadow-card-hover">
                <div class="border-b border-ink-100 px-4 py-2.5 sm:hidden">
                    <p class="truncate text-sm font-semibold text-ink-900">{{ auth()->user()->name }}</p>
                    <p class="truncate text-xs text-ink-500">{{ auth()->user()->email }}</p>
                </div>
                <a href="{{ route('profile.edit') }}" class="flex items-center gap-2.5 px-4 py-2 text-sm text-ink-700 transition-colors hover:bg-ink-50">
                    <svg class="h-4 w-4 text-ink-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8 8a8 8 0 0 0-16 0" />
                    </svg>
                    Profil Saya
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-sm text-red-600 transition-colors hover:bg-red-50">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12H3m0 0 4-4m-4 4 4 4m6-9h5a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2h-5" />
                        </svg>
                        Keluar
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>

@push('scripts')
<script>
    /**
     * Kotak pencarian lintas modul di topbar.
     *
     * AGENT: asisten | 2026-10-16 | tambah pencarian lintas modul | modul Pencarian
     *
     * Tiga penjaga yang penting di sini:
     * - `@input.debounce.300ms`: satu permintaan per jeda ketik, bukan per huruf.
     * - `AbortController`: permintaan lama dibatalkan saat kata kunci berubah,
     *   sehingga hasil "ju" tidak pernah menimpa hasil "jurnal".
     * - Pemeriksaan `term` di `.then`: jaring pengaman bila respons lama tetap
     *   tiba (abort bisa kalah cepat dengan respons yang sudah dikirim server).
     */
    function liveSearch() {
        return {
            term: @js((string) request('q', '')),
            open: false,
            loading: false,
            groups: [],
            hint: '',
            moreUrl: @js(route('search')),
            controller: null,

            fetchResults() {
                const term = this.term.trim();

                if (term.length < 3) {
                    this.groups = [];
                    this.hint = '';
                    this.open = false;
                    this.loading = false;

                    if (this.controller) {
                        this.controller.abort();
                        this.controller = null;
                    }

                    return;
                }

                if (this.controller) {
                    this.controller.abort();
                }

                this.controller = new AbortController();
                this.loading = true;
                this.open = true;

                fetch(@js(route('search.live')) + '?q=' + encodeURIComponent(term), {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    signal: this.controller.signal,
                })
                    .then((response) => (response.ok ? response.json() : Promise.reject(response)))
                    .then((data) => {
                        if (this.term.trim() !== term) {
                            return;
                        }

                        this.groups = data.groups || [];
                        this.hint = data.hint || '';
                        this.moreUrl = data.more || this.moreUrl;
                    })
                    .catch((error) => {
                        if (error && error.name === 'AbortError') {
                            return;
                        }

                        if (this.term.trim() === term) {
                            this.groups = [];
                            this.hint = 'Pencarian tidak dapat dimuat. Coba lagi.';
                        }
                    })
                    .finally(() => {
                        if (this.term.trim() === term) {
                            this.loading = false;
                        }
                    });
            },

            close() {
                this.open = false;
            },
        };
    }
</script>
@endpush
