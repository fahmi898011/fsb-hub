@extends('layouts.app')

@section('title', 'Master Tim')

@section('content')
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-ink-900">Master Tim</h1>
            <p class="text-sm text-ink-500">Tim kerja beserta anggotanya, di bawah satu divisi.</p>
        </div>

        @can('create', App\Models\Team::class)
            <a href="{{ route('admin.teams.create') }}" class="btn-primary shrink-0">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                </svg>
                Tambah Tim
            </a>
        @endcan
    </div>

    @if (! auth()->user()->can('team.update'))
        {{-- Supervisor / project leader: jelaskan kenapa daftarnya pendek. --}}
        <div class="mb-4 rounded-xl bg-brand-50 px-4 py-3 text-sm text-brand-800 ring-1 ring-inset ring-brand-100">
            Anda melihat tim yang Anda pimpin atau ikuti saja.
        </div>
    @endif

    <form method="GET" class="card card-body mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="flex-1">
            <label for="search" class="form-label">Cari</label>
            <input id="search" name="search" type="text" value="{{ $filters['search'] ?? '' }}" class="form-input" placeholder="Nama atau deskripsi tim">
        </div>

        <div class="sm:w-52">
            <label for="department" class="form-label">Divisi</label>
            <select id="department" name="department" class="form-input">
                <option value="">Semua divisi</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected((string) ($filters['department'] ?? '') === (string) $department->id)>
                        {{ $department->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="sm:w-40">
            <label for="status" class="form-label">Status</label>
            <select id="status" name="status" class="form-input">
                <option value="">Semua</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Nonaktif</option>
            </select>
        </div>

        <button type="submit" class="btn-secondary sm:shrink-0">Terapkan</button>
    </form>

    @if ($teams->isEmpty())
        <x-empty-state title="Belum ada tim" description="Buat tim agar supervisor dan project leader punya dasar untuk melihat laporan anggotanya.">
            @can('create', App\Models\Team::class)
                <a href="{{ route('admin.teams.create') }}" class="btn-primary">Tambah Tim</a>
            @endcan
        </x-empty-state>
    @else
        <div class="card hidden overflow-hidden lg:block">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-ink-100 bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Tim</th>
                        <th class="px-5 py-3 font-semibold">Divisi</th>
                        <th class="px-5 py-3 font-semibold">Ketua</th>
                        <th class="px-5 py-3 font-semibold">Anggota</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 font-semibold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @foreach ($teams as $team)
                        @include('admin.teams._row', ['team' => $team])
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="space-y-3 lg:hidden">
            @foreach ($teams as $team)
                <div class="card card-body">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-ink-900">{{ $team->name }}</p>
                            <p class="text-xs text-ink-400">
                                {{ $team->department?->name ?? 'Tanpa divisi' }}
                                &middot; {{ $team->memberships_count }} anggota
                            </p>
                            <p class="mt-1 text-xs text-ink-500">Ketua: {{ $team->leader?->name ?? 'belum ditentukan' }}</p>
                        </div>
                        <x-badge :variant="$team->status === 'active' ? 'green' : 'gray'">
                            {{ $team->status === 'active' ? 'Aktif' : 'Nonaktif' }}
                        </x-badge>
                    </div>

                    <div class="mt-3 flex gap-2">
                        @can('update', $team)
                            <a href="{{ route('admin.teams.edit', $team) }}" class="btn-secondary btn-sm">Edit</a>
                        @endcan

                        @can('delete', $team)
                            <form method="POST" action="{{ route('admin.teams.destroy', $team) }}"
                                  onsubmit="return confirm('Hapus tim ini?');">
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
            {{ $teams->links() }}
        </div>
    @endif
@endsection
