<!DOCTYPE html>
<html lang="id" class="h-full">
{{-- AGENT: asisten | 2026-10-06 | latar & logo rata untuk Tema A | UI/UX --}}
{{-- AGENT: asisten | 2026-10-10 | kartu auth rata (buang `shadow-card-hover`), brand pindah ke header kartu, tambah `theme-color` + landmark `<main>` | halaman masuk masih melanggar aturan kartu rata progres §11.10 | UI/UX --}}
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f766e">

    <title>{{ $title ?? 'Masuk' }} — {{ config('app.name', 'FSB Pusat Informasi') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">
    <div class="flex min-h-screen flex-col items-center justify-center bg-ink-50 px-4 py-10">
        {{-- Latar DATAR (PRD Frontend §10). Sebelumnya ada gradient + dua
             lingkaran blur; keduanya dihapus karena membuat halaman masuk
             terasa seperti landing page, bukan pintu masuk sistem internal.
             `overflow-hidden` ikut dihapus 2026-10-10: ia hanya ada untuk
             memotong blob tersebut dan berisiko memotong kartu di layar
             pendek (mis. laptop dengan toolbar browser). --}}

        <main class="w-full max-w-md">
            {{-- Kartu RATA tanpa bayangan dekoratif (progres §11.10). Brand
                 ditaruh di DALAM kartu supaya logo dan formulir tidak lagi
                 tampak sebagai dua blok terpisah yang mengambang. --}}
            <div class="card animate-in">
                <a href="{{ url('/') }}" class="flex items-center gap-3 border-b border-ink-100 px-5 py-4 sm:px-6">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand-600 text-sm font-bold text-white">FSB</span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-semibold leading-tight text-ink-900">Pusat Informasi</span>
                        <span class="block truncate text-xs leading-tight text-ink-500">FSB Internal</span>
                    </span>
                </a>

                <div class="card-body">
                    {{ $slot }}
                </div>
            </div>
        </main>

        <p class="mt-6 text-xs text-ink-400">&copy; {{ date('Y') }} FSB. Seluruh hak dilindungi.</p>
    </div>
</body>
</html>