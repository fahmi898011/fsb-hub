@extends('layouts.app')

@section('title', 'Master Divisi')

@section('content')
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-ink-900">Master Divisi</h1>
            <p class="text-sm text-ink-500">Struktur divisi perusahaan. Dipakai untuk rekap laporan per divisi.</p>
        </div>

        @can('create', App\Models\Department::class)
            <a href="{{ route('admin.departments.create') }}" class="btn-primary shrink-0">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                </svg>
                Tambah Divisi
            </a>
        @endcan
    </div>

    <form method="GET" class="card card-body mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="flex-1">
            <label for="search" class="form-label">Cari</label>
            <input id="search" name="search" type="text" value="{{ $filters['search'] ?? '' }}" class="form-input" placeholder="Nama, kode, atau deskripsi">
        </div>

        <div class="sm:w-44">
            <label for="status" class="form-label">Status</label>
            <select id="status" name="status" class="form-input">
                <option value="">Semua</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Nonaktif</option>
            </select>
        </div>

        <button type="submit" class="btn-secondary sm:shrink-0">Terapkan</button>
    </form>

    @if ($departments->isEmpty())
        <x-empty-state title="Belum ada divisi" description="Tambahkan divisi pertama untuk merapikan struktur organisasi.">
            @can('create', App\Models\Department::class)
                <a href="{{ route('admin.departments.create') }}" class="btn-primary">Tambah Divisi</a>
            @endcan
        </x-empty-state>
    @else
        <div class="card hidden overflow-hidden lg:block">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-ink-100 bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Divisi</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 font-semibold">Karyawan</th>
                        <th class="px-5 py-3 font-semibold">Tim</th>
                        <th class="px-5 py-3 font-semibold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @foreach ($departments as $department)
                        @include('admin.departments._row', ['department' => $department])
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="space-y-3 lg:hidden">
            @foreach ($departments as $department)
                <div class="card card-body">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-ink-900">{{ $department->name }}</p>
                            <p class="text-xs text-ink-400">
                                {{ $department->users_count }} karyawan &middot; {{ $department->teams_count }} tim
                            </p>
                        </div>
                        <x-badge :variant="$department->status === 'active' ? 'green' : 'gray'">
                            {{ $department->status === 'active' ? 'Aktif' : 'Nonaktif' }}
                        </x-badge>
                    </div>

                    <div class="mt-3 flex gap-2">
                        @can('update', $department)
                            <a href="{{ route('admin.departments.edit', $department) }}" class="btn-secondary btn-sm">Edit</a>
                        @endcan

                        @can('delete', $department)
                            <form method="POST" action="{{ route('admin.departments.destroy', $department) }}"
                                  onsubmit="return confirm('Hapus divisi ini?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-ghost btn-sm text-red-600 hover:bg-red-50">Hapus</button>
                            </form>
                        @endcan
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $departments->links() }}
        </div>
    @endif
@endsection
