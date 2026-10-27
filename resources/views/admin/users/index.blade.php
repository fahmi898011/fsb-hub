@extends('layouts.app')

@section('title', 'Manajemen Pengguna')

@section('content')
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-ink-900">Manajemen Pengguna</h1>
            <p class="text-sm text-ink-500">Kelola akun, role, dan status akses karyawan.</p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.users.import') }}" class="btn-secondary shrink-0">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21V9m0 0 4 4m-4-4-4 4M4 5h16" />
                </svg>
                Impor Excel
            </a>

            <a href="{{ route('admin.users.create') }}" class="btn-primary shrink-0">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                </svg>
                Tambah Pengguna
            </a>
        </div>
    </div>

    {{-- Ringkasan --}}
    <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([
            ['Total Pengguna', $stats['total'], 'brand'],
            ['Aktif', $stats['active'], 'green'],
            ['Nonaktif', $stats['inactive'], 'amber'],
            ['QA', $stats['qa'], 'gray'],
        ] as [$label, $value, $variant])
            <div class="card card-body">
                <p class="text-xs font-medium uppercase tracking-wide text-ink-500">{{ $label }}</p>
                <p class="mt-1 text-2xl font-semibold text-ink-900">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ route('admin.users.index') }}" class="card card-body mb-5">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <label for="search" class="form-label">Cari</label>
                <input id="search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" class="form-input" placeholder="Nama, email, NIP, atau departemen">
            </div>

            <div>
                <label for="filter-role" class="form-label">Role</label>
                <select id="filter-role" name="role" class="form-input">
                    <option value="">Semua role</option>
                    <option value="none" @selected(($filters['role'] ?? '') === 'none')>Tanpa peran</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->name }}" @selected(($filters['role'] ?? '') === $role->name)>{{ \App\Models\User::roleLabelFor($role->name) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="filter-status" class="form-label">Status</label>
                <select id="filter-status" name="status" class="form-input">
                    <option value="">Semua status</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Nonaktif</option>
                </select>
            </div>
        </div>

        <div class="mt-3 flex justify-end gap-2">
            <a href="{{ route('admin.users.index') }}" class="btn-secondary btn-sm">Reset</a>
            <button type="submit" class="btn-primary btn-sm">Terapkan</button>
        </div>
    </form>

    @if ($users->isEmpty())
        <x-empty-state title="Pengguna tidak ditemukan" description="Tidak ada akun yang cocok dengan filter yang dipilih.">
            <a href="{{ route('admin.users.create') }}" class="btn-primary">Tambah Pengguna</a>
        </x-empty-state>
    @else

        {{-- Tabel (desktop) --}}
        <div class="card hidden overflow-hidden lg:block">
            <table class="w-full text-sm">
                <thead class="border-b border-ink-100 bg-ink-50/60 text-left text-xs uppercase tracking-wide text-ink-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Pengguna</th>
                        <th class="px-4 py-3 font-semibold">NIP</th>
                        <th class="px-4 py-3 font-semibold">Departemen</th>
                        <th class="px-4 py-3 font-semibold">Role</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 text-right font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @foreach ($users as $user)
                        <tr class="transition-colors hover:bg-ink-50/50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <img src="{{ $user->avatar_url }}" alt="" class="h-9 w-9 rounded-full object-cover">
                                    <div class="min-w-0">
                                        <a href="{{ route('admin.users.show', $user) }}" class="block truncate font-medium text-ink-900 hover:text-brand-600">{{ $user->name }}</a>
                                        <p class="truncate text-xs text-ink-500">{{ $user->email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-ink-600">{{ $user->employee_id ?: '—' }}</td>
                            <td class="px-4 py-3 text-ink-600">{{ $user->departmentName() ?: '—' }}</td>
                            <td class="px-4 py-3">
                                @php($role = $user->roles->first()?->name)
                                <x-badge :variant="$role === 'admin' ? 'brand' : ($role === 'qa' ? 'amber' : 'gray')">
                                    {{ $role ? \App\Models\User::roleLabelFor($role) : 'Tanpa role' }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-3">
                                <x-badge :variant="$user->isActive() ? 'green' : 'red'">
                                    {{ $user->isActive() ? 'Aktif' : 'Nonaktif' }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.users.edit', $user) }}" class="btn-secondary btn-sm">Edit</a>
                                    @include('admin.users._actions', ['user' => $user])
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Kartu (mobile) --}}
        <div class="space-y-3 lg:hidden">
            @foreach ($users as $user)
                <div class="card card-body">
                    <div class="flex items-start gap-3">
                        <img src="{{ $user->avatar_url }}" alt="" class="h-10 w-10 rounded-full object-cover">
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('admin.users.show', $user) }}" class="block truncate font-medium text-ink-900">{{ $user->name }}</a>
                            <p class="truncate text-xs text-ink-500">{{ $user->email }}</p>
                            <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                @php($role = $user->roles->first()?->name)
                                <x-badge :variant="$role === 'admin' ? 'brand' : ($role === 'qa' ? 'amber' : 'gray')">
                                    {{ $role ? \App\Models\User::roleLabelFor($role) : 'Tanpa role' }}
                                </x-badge>
                                <x-badge :variant="$user->isActive() ? 'green' : 'red'">
                                    {{ $user->isActive() ? 'Aktif' : 'Nonaktif' }}
                                </x-badge>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-2 border-t border-ink-100 pt-3 text-xs text-ink-500">
                        <p><span class="font-medium text-ink-700">NIP:</span> {{ $user->employee_id ?: '—' }}</p>
                        <p><span class="font-medium text-ink-700">Dept:</span> {{ $user->departmentName() ?: '—' }}</p>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <a href="{{ route('admin.users.edit', $user) }}" class="btn-secondary btn-sm">Edit</a>
                        @include('admin.users._actions', ['user' => $user])
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-5">
            {{ $users->links() }}
        </div>
    @endif
@endsection

