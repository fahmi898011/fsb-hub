@extends('layouts.app')

{{-- Master Channel KPI (PRD Employee KPI/QA §4). --}}
{{-- AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master --}}

@section('title', 'Channel KPI')

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-semibold text-ink-900">Channel KPI</h1>
        <p class="text-sm text-ink-500">
            Channel adalah konteks penilaian (Inbound, Outbound, Chat, Email). Setiap channel punya scorecard sendiri.
        </p>
    </div>

    @include('admin.kpi._nav')

    <div class="mb-4 flex justify-end">
        @can('create', App\Models\Channel::class)
            <a href="{{ route('admin.kpi.channels.create') }}" class="btn-primary">Tambah Channel</a>
        @endcan
    </div>

    <form method="GET" class="card card-body mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="flex-1">
            <label for="search" class="form-label">Cari</label>
            <input id="search" name="search" type="text" value="{{ $filters['search'] ?? '' }}" class="form-input" placeholder="Nama atau deskripsi">
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

    @if ($channels->isEmpty())
        <x-empty-state title="Belum ada channel" description="Tambahkan channel seperti Inbound atau Chat untuk mulai menyusun scorecard." />
    @else
        <div class="card overflow-hidden">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-ink-100 bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Channel</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 font-semibold">Scorecard</th>
                        <th class="px-5 py-3 font-semibold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @foreach ($channels as $channel)
                        <tr class="hover:bg-ink-50">
                            <td class="px-5 py-3">
                                <p class="font-medium text-ink-800">{{ $channel->name }}</p>
                                <p class="text-xs text-ink-400">{{ $channel->description ?: $channel->slug }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <x-badge :variant="$channel->status === 'active' ? 'green' : 'gray'">
                                    {{ $channel->status === 'active' ? 'Aktif' : 'Nonaktif' }}
                                </x-badge>
                            </td>
                            <td class="px-5 py-3 text-ink-600">{{ $channel->scorecards_count }}</td>
                            <td class="px-5 py-3 text-right">
                                @can('update', $channel)
                                    <a href="{{ route('admin.kpi.channels.edit', $channel) }}" class="btn-secondary btn-sm">Edit</a>
                                @endcan
                                @can('delete', $channel)
                                    <form method="POST" action="{{ route('admin.kpi.channels.destroy', $channel) }}" class="inline"
                                          onsubmit="return confirm('Hapus channel ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-ghost btn-sm text-red-600 hover:bg-red-50">Hapus</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $channels->links() }}</div>
    @endif
@endsection
