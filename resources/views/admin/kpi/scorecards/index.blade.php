@extends('layouts.app')

{{-- Master Scorecard (PRD Employee KPI/QA §5). --}}
{{-- AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master --}}

@section('title', 'Scorecard')

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-semibold text-ink-900">Scorecard QA</h1>
        <p class="text-sm text-ink-500">Scorecard = pasangan KPI dan channel. Bobot parameter berada di dalam versinya.</p>
    </div>

    @include('admin.kpi._nav')

    <div class="mb-4 flex justify-end">
        @can('create', App\Models\Scorecard::class)
            <a href="{{ route('admin.kpi.scorecards.create') }}" class="btn-primary">Tambah Scorecard</a>
        @endcan
    </div>

    <form method="GET" class="card card-body mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="flex-1">
            <label for="search" class="form-label">Cari</label>
            <input id="search" name="search" type="text" value="{{ $filters['search'] ?? '' }}" class="form-input" placeholder="Nama scorecard">
        </div>
        <div class="sm:w-48">
            <label for="channel" class="form-label">Channel</label>
            <select id="channel" name="channel" class="form-input">
                <option value="">Semua</option>
                @foreach ($channels as $channel)
                    <option value="{{ $channel->id }}" @selected((int) ($filters['channel'] ?? 0) === $channel->id)>{{ $channel->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="sm:w-44">
            <label for="status" class="form-label">Status</label>
            <select id="status" name="status" class="form-input">
                <option value="">Semua</option>
                @foreach (['draft' => 'Draft', 'active' => 'Aktif', 'inactive' => 'Nonaktif', 'archived' => 'Arsip'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn-secondary sm:shrink-0">Terapkan</button>
    </form>

    @if ($scorecards->isEmpty())
        <x-empty-state title="Belum ada scorecard" description="Buat scorecard untuk sebuah KPI dan channel, lalu susun parameternya." />
    @else
        <div class="card overflow-hidden">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-ink-100 bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Scorecard</th>
                        <th class="px-5 py-3 font-semibold">KPI</th>
                        <th class="px-5 py-3 font-semibold">Channel</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 font-semibold">Versi</th>
                        <th class="px-5 py-3 font-semibold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @foreach ($scorecards as $scorecard)
                        <tr class="hover:bg-ink-50">
                            <td class="px-5 py-3">
                                <a href="{{ route('admin.kpi.scorecards.show', $scorecard) }}" class="font-medium text-brand-700 hover:underline">
                                    {{ $scorecard->name }}
                                </a>
                            </td>
                            <td class="px-5 py-3 text-ink-600">{{ $scorecard->kpi?->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-ink-600">{{ $scorecard->channel?->name ?? '—' }}</td>
                            <td class="px-5 py-3">
                                @php
                                    $statusMap = [
                                        'draft' => ['amber', 'Draft'],
                                        'active' => ['green', 'Aktif'],
                                        'inactive' => ['gray', 'Nonaktif'],
                                        'archived' => ['gray', 'Arsip'],
                                    ];
                                    [$variant, $label] = $statusMap[$scorecard->status] ?? ['gray', $scorecard->status];
                                @endphp
                                <x-badge :variant="$variant">{{ $label }}</x-badge>
                            </td>
                            <td class="px-5 py-3 text-ink-600">{{ $scorecard->versions_count }}</td>
                            <td class="px-5 py-3 text-right">
                                @can('update', $scorecard)
                                    <a href="{{ route('admin.kpi.scorecards.edit', $scorecard) }}" class="btn-secondary btn-sm">Edit</a>
                                @endcan
                                @can('delete', $scorecard)
                                    <form method="POST" action="{{ route('admin.kpi.scorecards.destroy', $scorecard) }}" class="inline"
                                          onsubmit="return confirm('Hapus scorecard ini?');">
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

        <div class="mt-4">{{ $scorecards->links() }}</div>
    @endif
@endsection
