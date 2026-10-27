<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 — Halaman Tidak Ditemukan</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">
    <div class="flex min-h-screen flex-col items-center justify-center gap-4 px-6 text-center">
        <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-amber-50 text-3xl font-bold text-amber-500">404</span>
        <h1 class="text-xl font-semibold text-ink-900">Halaman tidak ditemukan</h1>
        <p class="max-w-md text-sm text-ink-500">Halaman yang Anda cari mungkin telah dipindahkan atau tidak pernah ada.</p>
        <a href="{{ auth()->check() ? route('home') : route('login') }}" class="btn-primary mt-2">Kembali ke beranda</a>
    </div>
</body>
</html>