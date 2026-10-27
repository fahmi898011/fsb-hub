@php($user = auth()->user())

<aside
    x-show="sidebarOpen || true"
    class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-ink-950 text-white transition-all duration-300 ease-out"
    :class="{
        'translate-x-0': sidebarOpen,
        '-translate-x-full lg:translate-x-0': ! sidebarOpen,
        'lg:w-[4.5rem]': collapsed,
    }"
>
    {{-- Brand --}}
    <div class="flex h-16 shrink-0 items-center gap-3 border-b border-white/10 px-4">
        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-600 text-sm font-bold text-white">
            FSB
        </div>
        <div class="min-w-0" x-show="!collapsed">
            <p class="truncate text-sm font-semibold leading-tight">Pusat Informasi</p>
            <p class="truncate text-xs leading-tight text-ink-400">FSB Internal</p>
        </div>

        {{-- Tutup (mobile) --}}
        <button type="button" @click="sidebarOpen = false" class="btn-icon ml-auto text-ink-400 hover:bg-white/10 hover:text-white lg:hidden" aria-label="Tutup menu">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    {{-- Profil singkat --}}
    <div x-show="!collapsed" class="mx-3 mt-3 flex items-center gap-3 rounded-xl bg-white/[0.04] p-3 ring-1 ring-inset ring-white/5">
        <img src="{{ $user->avatar_url }}" alt="" class="h-9 w-9 shrink-0 rounded-full object-cover ring-2 ring-white/10">
        <div class="min-w-0">
            <p class="truncate text-sm font-medium leading-tight">{{ $user->name }}</p>
            <p class="truncate text-xs leading-tight text-ink-400">{{ $user->role_label }}</p>
        </div>
    </div>

    {{-- Navigasi --}}
    <nav class="sidebar-scroll flex-1 space-y-1 overflow-y-auto px-3 py-4">
        <a href="{{ route('home') }}" class="sidebar-link {{ request()->routeIs('home') ? 'sidebar-link-active' : '' }}">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z" />
            </svg>
            <span x-show="!collapsed" class="truncate">Beranda</span>
        </a>

        {{-- Halaman muka per peran. Sebelumnya supervisor dan HR mendarat di
             Beranda yang sama dengan karyawan, padahal pekerjaannya berbeda:
             supervisor membina timnya, HR membaca agregat lintas divisi.
             Gate-nya khusus peran supaya HR tidak masuk ke halaman supervisor
             (yang memuat nama anggota tim).
             AGENT: asisten | 2026-10-20 | tambah tautan dashboard supervisor & HR | modul Dashboard per peran --}}
        @can('supervisor-access')
            <a href="{{ route('supervisor.dashboard') }}" class="sidebar-link {{ request()->routeIs('supervisor.*') ? 'sidebar-link-active' : '' }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2M10 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM21 20v-2a4 4 0 0 0-3-3.87" />
                </svg>
                <span x-show="!collapsed" class="truncate">Dashboard Tim</span>
            </a>
        @endcan

        @can('hr-access')
            <a href="{{ route('hr.dashboard') }}" class="sidebar-link {{ request()->routeIs('hr.*') ? 'sidebar-link-active' : '' }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 19V5m0 14h16M8 16V9m4 7V6m4 10v-5" />
                </svg>
                <span x-show="!collapsed" class="truncate">Dashboard HR</span>
            </a>
        @endcan

        <a href="{{ route('information.index') }}" class="sidebar-link {{ request()->routeIs('information.index', 'information.category', 'information.show') ? 'sidebar-link-active' : '' }}">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16M4 12h16M4 19h10" />
            </svg>
            <span x-show="!collapsed" class="truncate">Semua Informasi</span>
        </a>

        {{-- Kategori dari database (PRD Redesign STEP 4).
             Dibuat akordeon: berapa pun jumlah kategori, sidebar tetap ringkas.
             Auto-terbuka bila halaman aktif sedang berada di dalam kategori. --}}
        @if (isset($sidebarCategories) && $sidebarCategories->isNotEmpty())
            @php($activeCategory = request()->routeIs('information.category') ? request()->route('slug') : null)

            {{-- Auto-terbuka bila halaman aktif berada di dalam salah satu kategori.
                 Nilai awal dihitung PHP, bukan lewat querySelector di x-init. --}}
            <div x-data="{ categoryOpen: {{ $activeCategory ? 'true' : 'false' }} }"
                 x-show="!collapsed">
                <button type="button"
                        @click="categoryOpen = ! categoryOpen"
                        class="sidebar-link w-full"
                        :aria-expanded="categoryOpen ? 'true' : 'false'">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 0 1 2-2h4l2 2h6a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                    </svg>
                    <span class="truncate">Kategori</span>
                    <svg class="ml-auto h-4 w-4 shrink-0 transition-transform duration-200"
                         :class="categoryOpen ? 'rotate-180' : ''"
                         fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                    </svg>
                </button>

                {{-- x-collapse tidak dipakai: plugin @alpinejs/collapse belum
                     terpasang, dan menambah dependensi hanya untuk animasi tidak
                     sepadan. x-show + max-height cukup. --}}
                <div x-show="categoryOpen"
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 -translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="mt-1 max-h-64 space-y-0.5 overflow-y-auto">
                    @foreach ($sidebarCategories as $category)
                        @php($isActive = $activeCategory === $category->slug)

                        <a href="{{ route('information.category', $category->slug) }}"
                           class="flex items-center gap-2 rounded-lg py-1.5 pl-10 pr-3 text-[13px] transition-colors
                                  {{ $isActive
                                        ? 'bg-white/10 font-medium text-white'
                                        : 'text-ink-400 hover:bg-white/5 hover:text-white' }}">
                            <span class="h-1 w-1 shrink-0 rounded-full bg-current opacity-60"></span>
                            <span class="truncate">{{ $category->name }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Mode ikon (sidebar dilipat): tampilkan satu ikon ke halaman semua informasi --}}
            <a href="{{ route('information.index') }}" x-show="collapsed" class="sidebar-link" title="Informasi">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 0 1 2-2h4l2 2h6a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                </svg>
            </a>
        @endif

        <a href="{{ route('search') }}" class="sidebar-link {{ request()->routeIs('search') ? 'sidebar-link-active' : '' }}">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.3-4.3M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14Z" />
            </svg>
            <span x-show="!collapsed" class="truncate">Pencarian</span>
        </a>

        {{-- Dukungan IT (PRD §42 — Phase 5). Terbuka untuk semua karyawan:
             bertanya ke IT tidak butuh izin khusus. Tiket tetap rahasia
             karena hanya tiket milik sendiri yang tampil. --}}
        <a href="{{ route('tickets.index') }}" class="sidebar-link {{ request()->routeIs('tickets.*') ? 'sidebar-link-active' : '' }}">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v11H8l-4 4z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v3m0 3h.01" />
            </svg>
            <span x-show="!collapsed" class="truncate">Tiket Saya</span>
        </a>

        {{-- Chat internal (PRD Chat). Terbuka untuk semua karyawan: tidak ada
             permission untuk MEMBACA percakapan, karena keanggotaan adalah
             penjaganya (lihat ConversationPolicy). Badge berisi jumlah pesan
             belum dibaca: angka awalnya dihitung saat sidebar dirender, lalu
             disegarkan oleh poller badge di layout tanpa memuat ulang halaman
             (PRD Chat §9). --}}
        <a href="{{ route('chat.index') }}" class="sidebar-link {{ request()->routeIs('chat.*') ? 'sidebar-link-active' : '' }}">
            <span class="relative shrink-0">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h6m-9 8V6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H7l-3 3Z" />
                </svg>
                {{-- Badge ini SELALU dirender (disembunyikan saat nol), bukan
                     dibungkus `@if`, supaya skrip polling badge di layout punya
                     elemen untuk diperbarui tanpa memuat ulang halaman
                     (PRD Chat §9). `x-show` tetap mengatur versi mana yang
                     tampil saat angkanya bukan nol; kelas `hidden` yang
                     disetel JS hanya relevan ketika angkanya nol, dan pada
                     keadaan itu style inline Alpine memang tidak pernah
                     menyalakannya — jadi keduanya tidak bertabrakan. --}}
                <span x-show="collapsed" data-chat-badge data-badge-cap="9"
                      class="{{ ($unreadChatCount ?? 0) > 0 ? '' : 'hidden' }} absolute -right-1.5 -top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold leading-none text-white">
                    {{ $unreadChatCount > 9 ? '9+' : $unreadChatCount }}
                </span>
            </span>
            <span x-show="!collapsed" class="truncate">Chat</span>
            <span x-show="!collapsed" data-chat-badge data-badge-cap="99"
                  class="{{ ($unreadChatCount ?? 0) > 0 ? '' : 'hidden' }} ml-auto shrink-0 rounded-full bg-brand-600 px-2 py-0.5 text-[10px] font-bold leading-none text-white">
                {{ $unreadChatCount > 99 ? '99+' : $unreadChatCount }}
            </span>
        </a>

        {{-- Catatan Kerja (PRD Catatan). Terbuka untuk semua karyawan dan
             tanpa permission, karena isinya pribadi: yang menjaga bukan
             permission melainkan kepemilikan (lihat WorkNotePolicy). Sengaja
             TIDAK ada badge - berbeda dari Chat, tidak ada yang perlu
             diberitahukan dari catatan sendiri.

             AGENT: asisten | 2026-10-01 | tambah tautan sidebar Catatan | modul Catatan Kerja, tautan halaman /catatan --}}
        <a href="{{ route('notes.index') }}" class="sidebar-link {{ request()->routeIs('notes.*') ? 'sidebar-link-active' : '' }}">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 4h9l3 3v13H8a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6v12a2 2 0 0 0 2 2M11 9h5M11 13h5" />
            </svg>
            <span x-show="!collapsed" class="truncate">Catatan</span>
        </a>

        @if ($user->can('admin-access'))
            <div x-show="!collapsed" class="sidebar-section">Administrasi</div>
            <div x-show="collapsed" class="mx-auto my-2 h-px w-8 bg-white/10"></div>

            {{-- Halaman muka Admin: keadaan wadah aplikasi. Ditaruh paling atas
                 karena inilah tempat memeriksa "apakah ada yang salah" sebelum
                 masuk ke menu pengaturan mana pun. --}}
            <a href="{{ route('admin.dashboard') }}" class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'sidebar-link-active' : '' }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 13h6V4H4v9Zm0 7h6v-4H4v4Zm10 0h6v-9h-6v9Zm0-16v4h6V4h-6Z" />
                </svg>
                <span x-show="!collapsed" class="truncate">Dashboard Admin</span>
            </a>

            <a href="{{ route('admin.categories.index') }}" class="sidebar-link {{ request()->routeIs('admin.categories.*') ? 'sidebar-link-active' : '' }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16M4 10h16M4 15h10M4 20h6" />
                </svg>
                <span x-show="!collapsed" class="truncate">Master Informasi</span>
            </a>

            {{-- Master KPI & Scorecard (PRD Employee KPI/QA §5). Admin menyusun
                 bobot; QA melihatnya (read-only) untuk konteks penilaian. --}}
            {{-- AGENT: asisten | 2026-10-02 | tambah tautan Master KPI | modul KPI, fase 1 master --}}
            @can('scorecard.view')
                <a href="{{ route('admin.kpi.scorecards.index') }}" class="sidebar-link {{ request()->routeIs('admin.kpi.*') ? 'sidebar-link-active' : '' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 21V5a2 2 0 0 1 2-2h7l5 5v13M9 12h6M9 16h6M9 8h2" />
                    </svg>
                    <span x-show="!collapsed" class="truncate">Master KPI</span>
                </a>
            @endcan

            @can('user.view')
                <a href="{{ route('admin.users.index') }}" class="sidebar-link {{ request()->routeIs('admin.users.*') ? 'sidebar-link-active' : '' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 19v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1M9.5 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM21 19v-1a4 4 0 0 0-3-3.87M16.5 3.13a4 4 0 0 1 0 7.75" />
                    </svg>
                    <span x-show="!collapsed" class="truncate">Manajemen Pengguna</span>
                </a>
            @endcan

            @can('user.view')
                <a href="{{ route('admin.audit.index') }}" class="sidebar-link {{ request()->routeIs('admin.audit.*') ? 'sidebar-link-active' : '' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2M9 12h6M9 16h4" />
                    </svg>
                    <span x-show="!collapsed" class="truncate">Audit Log</span>
                </a>
            @endcan

            @can('user.view')
                <a href="{{ route('admin.roles.index') }}" class="sidebar-link {{ request()->routeIs('admin.roles.*') ? 'sidebar-link-active' : '' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 4v5c0 4.4-3 8.2-7 9-4-.8-7-4.6-7-9V7l7-4Zm-2 9 1.5 1.5L14 10" />
                    </svg>
                    <span x-show="!collapsed" class="truncate">Role &amp; Permission</span>
                </a>
            @endcan

            {{-- Pengaturan Fitur: saklar on/off yang mengubah perilaku seluruh
                 pengguna, jadi hanya Admin. Menu ini juga satu-satunya jalan
                 menghidupkan kembali Quiz pilihan ganda setelah diarsipkan. --}}
            {{-- AGENT: asisten | 2026-10-16 | tambah tautan pengaturan fitur | modul Pengaturan Fitur --}}
            @if ($user->isAdmin())
                <a href="{{ route('admin.features.index') }}" class="sidebar-link {{ request()->routeIs('admin.features.*') ? 'sidebar-link-active' : '' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.3 4.3a1 1 0 0 1 1-.8h1.4a1 1 0 0 1 1 .8l.2 1.3 1.2.7 1.2-.5a1 1 0 0 1 1.2.3l1 1.4a1 1 0 0 1-.1 1.3l-1 1v1.4l1 1a1 1 0 0 1 .1 1.3l-1 1.4a1 1 0 0 1-1.2.3l-1.2-.5-1.2.7-.2 1.3a1 1 0 0 1-1 .8h-1.4a1 1 0 0 1-1-.8l-.2-1.3-1.2-.7-1.2.5a1 1 0 0 1-1.2-.3l-1-1.4a1 1 0 0 1 .1-1.3l1-1V11l-1-1a1 1 0 0 1-.1-1.3l1-1.4a1 1 0 0 1 1.2-.3l1.2.5 1.2-.7.2-1.3ZM12 13.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z" />
                    </svg>
                    <span x-show="!collapsed" class="truncate">Pengaturan Fitur</span>
                </a>
            @endif
        @endif

        {{-- Struktur organisasi. Seksinya terpisah dari "Administrasi" karena
             HR dan supervisor/project leader juga perlu melihatnya walau bukan
             Admin; yang mereka lihat dibatasi policy. --}}
        @can('manage-organization')
            <div x-show="!collapsed" class="sidebar-section">Organisasi</div>
            <div x-show="collapsed" class="mx-auto my-2 h-px w-8 bg-white/10"></div>

            @can('department.view')
                <a href="{{ route('admin.departments.index') }}" class="sidebar-link {{ request()->routeIs('admin.departments.*') ? 'sidebar-link-active' : '' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-4h6v4M9 11h.01M15 11h.01" />
                    </svg>
                    <span x-show="!collapsed" class="truncate">Divisi</span>
                </a>
            @endcan

            @can('team.view')
                <a href="{{ route('admin.teams.index') }}" class="sidebar-link {{ request()->routeIs('admin.teams.*') ? 'sidebar-link-active' : '' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2M10 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM21 20v-2a4 4 0 0 0-3-3.87" />
                    </svg>
                    <span x-show="!collapsed" class="truncate">Tim</span>
                </a>
            @endcan
        @endcan

        @can('manage-tickets')
            <div x-show="!collapsed" class="sidebar-section">Dukungan IT</div>
            <div x-show="collapsed" class="mx-auto my-2 h-px w-8 bg-white/10"></div>
            <a href="{{ route('it.dashboard') }}" class="sidebar-link {{ request()->routeIs('it.dashboard') ? 'sidebar-link-active' : '' }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4h7v7H4zM13 4h7v4h-7zM13 12h7v8h-7zM4 15h7v5H4z" />
                </svg>
                <span x-show="!collapsed" class="truncate">Dashboard IT</span>
            </a>
            <a href="{{ route('it.tickets.index') }}" class="sidebar-link {{ request()->routeIs('it.tickets.*') ? 'sidebar-link-active' : '' }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v11H8l-4 4z" />
                </svg>
                <span x-show="!collapsed" class="truncate">Antrean Tiket IT</span>
            </a>
        @endcan

        {{-- QA Sampling (PRD Employee KPI/QA §14-§16). QA menilai sample;
             supervisor/admin memeriksa. Karyawan hanya melihat hasil sendiri. --}}
        {{-- AGENT: asisten | 2026-10-03 | tambah tautan QA Sampling | modul KPI, fase 2 QA sampling --}}
        {{-- AGENT: asisten | 2026-10-04 | pisahkan pola aktif + tautan Laporan QA | modul KPI, fase 3 QA reporting --}}
        @can('access-qa-sampling')
            <div x-show="!collapsed" class="sidebar-section">Penilaian QA</div>
            <div x-show="collapsed" class="mx-auto my-2 h-px w-8 bg-white/10"></div>
            {{-- Pola aktif dipersempit ke rute penilaian saja. Sebelumnya
                 `qa.sampling.*` ikut menyala di halaman laporan, sehingga dua
                 menu tampak aktif bersamaan dan pengguna bingung sedang di
                 mana. --}}
            <a href="{{ route('qa.sampling.index') }}"
               class="sidebar-link {{ request()->routeIs('qa.sampling.index', 'qa.sampling.create', 'qa.sampling.show') ? 'sidebar-link-active' : '' }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M4 6h16v12H4z" />
                </svg>
                <span x-show="!collapsed" class="truncate">QA Sampling</span>
            </a>
        @endcan

        {{-- Laporan & analitik QA (PRD §39-§42). Terpisah dari menu penilaian
             karena izinnya berbeda: karyawan boleh menilai/melihat hasilnya
             sendiri, tetapi laporan menampilkan data lintas karyawan. --}}
        @can('qa_sampling.report.view')
            <a href="{{ route('qa.sampling.report.dashboard') }}"
               class="sidebar-link {{ request()->routeIs('qa.sampling.report.*') ? 'sidebar-link-active' : '' }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 19V5m0 14h16M8 16V9m4 7V6m4 10v-5" />
                </svg>
                <span x-show="!collapsed" class="truncate">Laporan QA</span>
            </a>
        @endcan

        {{-- Penilaian kualitatif + antrean penilaian ujian (PRD QA Assessment
             §4-§5). Sebelumnya dua halaman ini hanya bisa dicapai lewat
             tautan di dalam modul, sehingga supervisor/HR tidak punya jalan
             masuk dari menu utama meski izinnya sudah ada.
             AGENT: asisten | 2026-10-20 | tambah tautan penilaian QA & ujian | modul Dashboard per peran --}}
        @can('qa.assessment.view')
            <a href="{{ route('qa.assessments.index') }}"
               class="sidebar-link {{ request()->routeIs('qa.assessments.*', 'qa.assessment.*') ? 'sidebar-link-active' : '' }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M4 6h16v12H4z" />
                </svg>
                <span x-show="!collapsed" class="truncate">Penilaian Kualitatif</span>
            </a>
        @endcan

        {{-- Ujian (dulu "Quiz"). Sejak Quiz pilihan ganda diarsipkan, menu ini
             menunjuk ke mesin ujian essay: agent membuka "Ujian Saya", QA/HR
             membuka pengelolaan ujian. Quiz lama hanya muncul bila saklar
             `quiz.multiple_choice` dinyalakan Admin — tanpa itu tautannya akan
             mengarah ke 404 karena rute quiz dijaga middleware `feature:`.
             AGENT: asisten | 2026-10-16 | alihkan menu Quiz ke Ujian | modul Quiz -> Ujian --}}
        @if ($user->hasRole('employee') || $user->can('qa.exam.assignment.view'))
            <div x-show="!collapsed" class="sidebar-section">Ujian</div>
            <div x-show="collapsed" class="mx-auto my-2 h-px w-8 bg-white/10"></div>
        @endif

        @if ($user->hasRole('employee'))
            <a href="{{ route('qa.exam.forms.my') }}" class="sidebar-link {{ request()->routeIs('qa.exam.forms.my') ? 'sidebar-link-active' : '' }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" /></svg>
                <span x-show="!collapsed" class="truncate">Ujian Saya</span>
            </a>
        @endif

        @can('viewAny', App\Models\ExamForm::class)
            <a href="{{ route('qa.exam.forms.index') }}" class="sidebar-link {{ request()->routeIs('qa.exam.*') && ! request()->routeIs('qa.exam.forms.my', 'qa.exam.grading.*') ? 'sidebar-link-active' : '' }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2M9 12h6M9 16h4" /></svg>
                <span x-show="!collapsed" class="truncate">Kelola Ujian</span>
            </a>
        @endcan

        {{-- Antrean penilaian ujian. Diberi tautan sendiri karena halaman ini
             adalah pekerjaan harian reviewer: tanpa menu, ia hanya bisa
             ditemukan lewat pintasan dashboard.
             AGENT: asisten | 2026-10-20 | tambah tautan penilaian ujian | modul Dashboard per peran --}}
        @can('qa.exam.assignment.view')
            <a href="{{ route('qa.exam.grading.index') }}" class="sidebar-link {{ request()->routeIs('qa.exam.grading.*') ? 'sidebar-link-active' : '' }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M4 6h16v12H4z" /></svg>
                <span x-show="!collapsed" class="truncate">Penilaian Ujian</span>
            </a>
        @endcan

        @if (App\Models\Setting::enabled('quiz.multiple_choice', false))
            @if ($user->can('quiz.take') || $user->can('quiz_result.view'))
                <a href="{{ route('quiz.index') }}" class="sidebar-link {{ request()->routeIs('quiz.*') ? 'sidebar-link-active' : '' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" /></svg>
                    <span x-show="!collapsed" class="truncate">Quiz Saya</span>
                </a>
            @endif

            @can('quiz.view')
                <a href="{{ route('qa.quiz.dashboard') }}" class="sidebar-link {{ request()->routeIs('qa.quiz.*') ? 'sidebar-link-active' : '' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 19V5m0 14h16M8 16V9m4 7V6m4 10v-5" /></svg>
                    <span x-show="!collapsed" class="truncate">Kelola Quiz</span>
                </a>
            @endcan
        @endif

        @if ($user->canManageInformation())
            <div x-show="!collapsed" class="sidebar-section">Pengelolaan</div>
            <div x-show="collapsed" class="mx-auto my-2 h-px w-8 bg-white/10"></div>
            <a href="{{ route('qa.dashboard') }}" class="sidebar-link {{ request()->routeIs('qa.dashboard') ? 'sidebar-link-active' : '' }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4h7v7H4zM13 4h7v4h-7zM13 12h7v8h-7zM4 15h7v5H4z" />
                </svg>
                <span x-show="!collapsed" class="truncate">Dashboard QA</span>
            </a>
            <a href="{{ route('qa.information.index') }}" class="sidebar-link {{ request()->routeIs('qa.information.*') ? 'sidebar-link-active' : '' }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                </svg>
                <span x-show="!collapsed" class="truncate">Kelola Informasi</span>
            </a>

            @can('tag.view')
                <a href="{{ route('qa.tags.index') }}" class="sidebar-link {{ request()->routeIs('qa.tags.*') ? 'sidebar-link-active' : '' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5.6a2 2 0 0 1 1.4.6l6.4 6.4a2 2 0 0 1 0 2.8l-5.6 5.6a2 2 0 0 1-2.8 0L5.6 12A2 2 0 0 1 5 10.6V5a2 2 0 0 1 2-2Z" />
                    </svg>
                    <span x-show="!collapsed" class="truncate">Kelola Tag</span>
                </a>
            @endcan
        @endif

        <div x-show="!collapsed" class="sidebar-section">Akun</div>
        <div x-show="collapsed" class="mx-auto my-2 h-px w-8 bg-white/10"></div>
        <a href="{{ route('profile.edit') }}" class="sidebar-link {{ request()->routeIs('profile.*') ? 'sidebar-link-active' : '' }}">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8 8a8 8 0 0 0-16 0" />
            </svg>
            <span x-show="!collapsed" class="truncate">Profil Saya</span>
        </a>
    </nav>

    {{-- Footer sidebar --}}
    <div class="shrink-0 border-t border-white/10 p-3">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="sidebar-link w-full text-ink-400 hover:text-white">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12H3m0 0 4-4m-4 4 4 4m6-9h5a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2h-5" />
                </svg>
                <span x-show="!collapsed" class="truncate">Keluar</span>
            </button>
        </form>
    </div>
</aside>