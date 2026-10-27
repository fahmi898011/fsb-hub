<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f766e">
    {{-- AGENT: asisten | 2026-10-06 | theme-color -> teal Tema A, hapus blur | UI/UX --}}

    <title>@yield('title', $title ?? 'Beranda') — {{ config('app.name', 'FSB Pusat Informasi') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>
<body class="h-full">
    <div
        x-data="{
            sidebarOpen: false,
            collapsed: false,
        }"
        x-init="collapsed = localStorage.getItem('fsb-sidebar-collapsed') === 'true'; $watch('collapsed', v => localStorage.setItem('fsb-sidebar-collapsed', v));"
        class="app-shell min-h-screen"
        :class="{ 'is-collapsed': collapsed }"
    >
        {{-- Overlay mobile --}}
        <div
            x-show="sidebarOpen"
            x-transition.opacity
            x-cloak
            @click="sidebarOpen = false"
            class="fixed inset-0 z-40 bg-ink-950/60 lg:hidden"
        ></div>

        {{-- Sidebar --}}
        @include('layouts.partials.sidebar')

        {{-- Konten --}}
        <div class="app-main">
            @include('layouts.partials.topbar')

            <main class="app-content">
                @if (session('success'))
                    <div class="mb-4 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 animate-in" role="status">
                        <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" /></svg>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-4 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 animate-in" role="alert">
                        <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    {{-- Penjadwal polling adaptif + pengaturan intervalnya (PRD Chat §9).
         Ditaruh SEBELUM poller mana pun supaya `window.fsbPollLoop` dan
         `window.fsbPollSettings` sudah ada saat skrip poller berjalan. --}}
    @include('layouts.partials.poll_loop')

    {{-- Badge "belum dibaca" di sidebar & topbar disegarkan tanpa memuat ulang
         halaman (PRD Chat §9). Ditaruh setelah seluruh konten supaya badge
         sudah ada di DOM saat skripnya berjalan. --}}
    @include('layouts.partials.badge_poller')

    @stack('scripts')
</body>
</html>