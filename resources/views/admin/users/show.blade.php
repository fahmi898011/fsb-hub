@extends('layouts.app')

@section('title', 'Detail Pengguna')

@section('content')
    <nav class="mb-4 flex items-center gap-2 text-sm text-ink-500">
        <a href="{{ route('admin.users.index') }}" class="hover:text-ink-800">Manajemen Pengguna</a>
        <span>/</span>
        <span class="text-ink-800">{{ $user->name }}</span>
    </nav>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
        {{-- Profil --}}
        <div class="card card-body lg:col-span-2">
            <div class="flex items-start gap-4">
                <img src="{{ $user->avatar_url }}" alt="" class="h-16 w-16 rounded-full object-cover">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-lg font-semibold text-ink-900">{{ $user->name }}</h1>
                        @php($role = $user->roles->first()?->name)
                        <x-badge :variant="$role === 'admin' ? 'brand' : ($role === 'qa' ? 'amber' : 'gray')">
                            {{ $role ? ucfirst($role) : 'Tanpa role' }}
                        </x-badge>
                        <x-badge :variant="$user->isActive() ? 'green' : 'red'">
                            {{ $user->isActive() ? 'Aktif' : 'Nonaktif' }}
                        </x-badge>
                    </div>
                    <p class="mt-1 text-sm text-ink-500">{{ $user->email }}</p>
                </div>
            </div>

            <dl class="mt-5 grid grid-cols-1 gap-4 border-t border-ink-100 pt-5 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-ink-500">NIP / ID Karyawan</dt>
                    <dd class="mt-0.5 text-sm text-ink-900">{{ $user->employee_id ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-ink-500">Departemen</dt>
                    <dd class="mt-0.5 text-sm text-ink-900">{{ $user->departmentName() ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-ink-500">Telepon</dt>
                    <dd class="mt-0.5 text-sm text-ink-900">{{ $user->phone ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-ink-500">Login Terakhir</dt>
                    <dd class="mt-0.5 text-sm text-ink-900">{{ $user->last_login?->diffForHumans() ?? 'Belum pernah' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-ink-500">Terdaftar</dt>
                    <dd class="mt-0.5 text-sm text-ink-900">{{ $user->created_at?->translatedFormat('d F Y') }}</dd>
                </div>
            </dl>
        </div>

        {{-- Aksi --}}
        <div class="card card-body">
            <h2 class="text-sm font-semibold text-ink-900">Aksi</h2>
            <div class="mt-3 flex flex-col gap-2">
                <a href="{{ route('admin.users.edit', $user) }}" class="btn-secondary justify-center">Edit Pengguna</a>

                @unless ($user->is(auth()->user()))
                    <form method="POST" action="{{ route('admin.users.status', $user) }}">
                        @csrf
                        <button type="submit" class="btn-secondary w-full justify-center">
                            {{ $user->isActive() ? 'Nonaktifkan Akun' : 'Aktifkan Akun' }}
                        </button>
                    </form>

                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                          onsubmit="return confirm('Hapus akun {{ $user->name }}? Tindakan ini tidak dapat dibatalkan.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-danger w-full justify-center">Hapus Akun</button>
                    </form>
                @else
                    <p class="text-xs text-ink-400">Ini akun Anda sendiri — tidak dapat dinonaktifkan atau dihapus.</p>
                @endunless
            </div>
        </div>
    </div>

    {{-- Permission --}}
    <div class="card card-body mt-5">
        <h2 class="text-sm font-semibold text-ink-900">Hak Akses</h2>
        <p class="mt-1 text-xs text-ink-500">Permission yang dimiliki melalui role <span class="font-medium">{{ $role ? ucfirst($role) : '—' }}</span>.</p>

        @php($permissions = $user->roles->first()?->permissions->pluck('name')->sort() ?? collect())
        @if ($permissions->isEmpty())
            <p class="mt-3 text-sm text-ink-500">Tidak ada permission khusus.</p>
        @else
            <div class="mt-3 flex flex-wrap gap-1.5">
                @foreach ($permissions as $permission)
                    <x-badge variant="gray">{{ $permission }}</x-badge>
                @endforeach
            </div>
        @endif
    </div>
@endsection
