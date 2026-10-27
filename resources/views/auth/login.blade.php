<x-guest-layout>
    {{-- AGENT: asisten | 2026-10-10 | perbaikan halaman masuk: buang tautan mati `href="#"`, error field pakai `x-input-error`, tambah toggle lihat password + aria | UI/UX --}}
    <div class="mb-6">
        <h1 class="text-lg font-semibold text-ink-900">Masuk ke akun Anda</h1>
        <p class="mt-1 text-sm text-ink-500">Gunakan email kantor yang terdaftar.</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="form-label">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                   class="form-input">
            <x-input-error id="email-error" :messages="$errors->get('email')" class="mt-1.5" />
        </div>

        {{-- Toggle lihat password: salah ketik adalah penyebab gagal masuk
             paling umum dan pengguna tidak punya cara memeriksa isinya.
             Alpine sudah ada di bundel (`resources/js/app.js`). --}}
        <div x-data="{ shown: false }">
            <label for="password" class="form-label">Password</label>
            <div class="relative">
                <input id="password" type="password" :type="shown ? 'text' : 'password'" name="password" required autocomplete="current-password"
                       @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                       class="form-input pr-12">
                <button type="button"
                        @click="shown = !shown"
                        :aria-pressed="shown ? 'true' : 'false'"
                        :aria-label="shown ? 'Sembunyikan password' : 'Tampilkan password'"
                        class="btn-icon absolute inset-y-0 right-0.5 my-auto text-ink-400 hover:text-ink-700">
                    <svg x-show="!shown" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12S6 5.25 12 5.25 21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                    <svg x-show="shown" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m3 3 18 18" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.6 6.1A9.9 9.9 0 0 1 12 6c6 0 9.75 6 9.75 6a17.6 17.6 0 0 1-2.4 3.1M6.3 7.9A17.4 17.4 0 0 0 2.25 12S6 18 12 18a9.9 9.9 0 0 0 3.4-.6" />
                    </svg>
                </button>
            </div>
            <x-input-error id="password-error" :messages="$errors->get('password')" class="mt-1.5" />
        </div>

        <div class="flex items-center justify-between gap-3">
            <label for="remember_me" class="inline-flex items-center gap-2 text-sm text-ink-600">
                <input id="remember_me" type="checkbox" name="remember" class="rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                Ingat saya
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="link text-sm">Lupa password?</a>
            @endif
        </div>

        <button type="submit" class="btn-primary w-full">Masuk</button>
    </form>

    {{-- Sebelumnya tautan `href="#"` ("Hubungi admin") yang mati: diklik hanya
         melompat ke atas halaman. Tidak ada rute kontak admin di aplikasi ini,
         jadi diganti keterangan tanpa tautan palsu. --}}
    <p class="mt-6 text-center text-sm text-ink-500">
        Belum punya akses? Akun dibuat oleh admin — hubungi admin IT Anda.
    </p>
</x-guest-layout>