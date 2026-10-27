@extends('layouts.app')

@section('title', 'Profil Saya')

@section('content')
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Kartu identitas --}}
        <aside class="lg:col-span-1">
            <div class="card card-body text-center">
                <img src="{{ $user->avatar_url }}" alt="" class="mx-auto h-20 w-20 rounded-full object-cover">
                <h1 class="mt-3 text-base font-semibold text-ink-900">{{ $user->name }}</h1>
                <p class="text-sm text-ink-500">{{ $user->email }}</p>
                <div class="mt-3 flex justify-center">
                    <x-badge variant="brand">{{ $user->role_label }}</x-badge>
                </div>

                <dl class="mt-5 space-y-3 text-left text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-500">ID Karyawan</dt>
                        <dd class="font-medium text-ink-800">{{ $user->employee_id ?: '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-500">Divisi</dt>
                        <dd class="font-medium text-ink-800">{{ $user->departmentName() ?: '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-500">Telepon</dt>
                        <dd class="font-medium text-ink-800">{{ $user->phone ?: '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-500">Status</dt>
                        <dd><x-badge :variant="$user->isActive() ? 'green' : 'red'">{{ $user->isActive() ? 'Aktif' : 'Nonaktif' }}</x-badge></dd>
                    </div>
                </dl>
            </div>

            {{-- Aktivitas --}}
            <div class="card mt-4">
                <div class="border-b border-ink-100 px-5 py-3">
                    <h2 class="text-sm font-semibold text-ink-900">Aktivitas Terakhir</h2>
                </div>
                <div class="divide-y divide-ink-100">
                    @forelse ($activityLogs as $log)
                        <div class="px-5 py-3">
                            <p class="text-sm text-ink-700">{{ ucfirst(str_replace('_', ' ', $log->action)) }}</p>
                            <p class="text-xs text-ink-400">{{ $log->created_at?->translatedFormat('d M Y, H:i') }}</p>
                        </div>
                    @empty
                        <p class="px-5 py-4 text-sm text-ink-400">Belum ada aktivitas.</p>
                    @endforelse
                </div>
            </div>
        </aside>

        {{-- Form --}}
        <div class="space-y-6 lg:col-span-2">
            <div class="card card-body">
                <h2 class="text-base font-semibold text-ink-900">Informasi Profil</h2>
                <p class="mt-0.5 text-sm text-ink-500">Perbarui data akun dan kontak Anda.</p>

                <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="name" class="form-label">Nama</label>
                            <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required class="form-input">
                            @error('name')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="email" class="form-label">Email</label>
                            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required class="form-input">
                            @error('email')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="phone" class="form-label">Telepon</label>
                            <input id="phone" name="phone" type="text" value="{{ old('phone', $user->phone) }}" class="form-input">
                            @error('phone')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            {{-- Divisi dikelola Admin/HR lewat Master Divisi, jadi
                                 di sini hanya ditampilkan (read-only) agar tidak
                                 muncul lagi teks bebas yang memecah rekap. --}}
                            <span class="form-label">Divisi</span>
                            <p class="rounded-lg bg-ink-50 px-3 py-2 text-sm text-ink-700">
                                {{ $user->departmentName() ?: 'Belum ditentukan' }}
                            </p>
                            <p class="mt-1 text-xs text-ink-400">Perubahan divisi diatur oleh Admin atau HR.</p>
                        </div>
                    </div>

                    <div>
                        <label for="avatar" class="form-label">Foto Profil</label>
                        <input id="avatar" name="avatar" type="file" accept="image/png,image/jpeg" class="form-input file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-brand-700">
                        @error('avatar')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="btn-primary">Simpan Perubahan</button>
                    </div>
                </form>
            </div>

            <div class="card card-body">
                <h2 class="text-base font-semibold text-ink-900">Ubah Password</h2>
                <p class="mt-0.5 text-sm text-ink-500">Gunakan password yang kuat dan mudah Anda ingat.</p>

                <form method="POST" action="{{ route('profile.update') }}" class="mt-5 space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="current_password" class="form-label">Password Saat Ini</label>
                        <input id="current_password" name="current_password" type="password" class="form-input" autocomplete="current-password">
                        @error('current_password')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="password" class="form-label">Password Baru</label>
                            <input id="password" name="password" type="password" class="form-input" autocomplete="new-password">
                            @error('password')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="password_confirmation" class="form-label">Konfirmasi Password</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" class="form-input" autocomplete="new-password">
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="btn-secondary">Ubah Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection