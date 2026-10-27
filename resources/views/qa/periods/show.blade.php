@extends('layouts.app')

{{-- Detail periode QA (PRD QA Assessment §7). --}}
{{-- AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, periode --}}

@section('title', 'Detail Periode QA')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-ink-900">{{ $qaPeriod->name }}</h1>
        <div class="flex items-center gap-2">
            <x-badge :variant="$qaPeriod->status === 'active' ? 'green' : 'gray'">
                {{ $qaPeriod->status === 'active' ? 'Aktif' : 'Nonaktif' }}
            </x-badge>
            @can('update', $qaPeriod)
                <a href="{{ route('qa.periods.edit', $qaPeriod) }}" class="btn-secondary btn-sm">Ubah</a>
            @endcan
            @can('delete', $qaPeriod)
                <form method="POST" action="{{ route('qa.periods.destroy', $qaPeriod) }}"
                      onsubmit="return confirm('Hapus periode ini?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-secondary btn-sm text-red-600">Hapus</button>
                </form>
            @endcan
        </div>
    </div>

    @include('qa.assessment._nav')

    <div class="card card-body mb-5 grid gap-4 text-sm sm:grid-cols-4">
        <div>
            <p class="text-xs uppercase tracking-wide text-ink-500">Rentang</p>
            <p class="font-medium text-ink-900">{{ $qaPeriod->start_date->format('d M Y') }} — {{ $qaPeriod->end_date->format('d M Y') }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-ink-500">Induk</p>
            <p class="font-medium text-ink-900">
                @if ($qaPeriod->parent)
                    <a href="{{ route('qa.periods.show', $qaPeriod->parent) }}" class="text-brand-700 hover:underline">{{ $qaPeriod->parent->name }}</a>
                @else
                    —
                @endif
            </p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-ink-500">Assessment</p>
            <p class="font-medium text-ink-900">{{ $qaPeriod->assessments_count }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-ink-500">Deskripsi</p>
            <p class="font-medium text-ink-900">{{ $qaPeriod->description ?: '—' }}</p>
        </div>
    </div>

    @if ($qaPeriod->children->isNotEmpty())
        <div class="card card-body mb-5">
            <h2 class="text-sm font-semibold text-ink-900">Periode anak</h2>
            <ul class="mt-2 space-y-1 text-sm text-ink-600">
                @foreach ($qaPeriod->children as $child)
                    <li>
                        <a href="{{ route('qa.periods.show', $child) }}" class="text-brand-700 hover:underline">{{ $child->name }}</a>
                        <span class="text-xs text-ink-500">({{ $child->start_date->format('d M Y') }} — {{ $child->end_date->format('d M Y') }})</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div>
        <a href="{{ route('qa.periods.index') }}" class="btn-secondary">Kembali</a>
    </div>
@endsection
