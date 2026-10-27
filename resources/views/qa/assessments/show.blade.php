@extends('layouts.app')

{{-- Detail penilaian kualitatif (PRD QA Assessment §4). --}}
{{-- AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian A --}}

@section('title', 'Detail Penilaian QA')

@section('content')
    @php
        // Assessment hanya boleh terbit bila sudah punya item penilaian.
        $itemCount = $qaAssessment->items->count();
    @endphp

    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-ink-900">Penilaian: {{ $qaAssessment->agent_name_snapshot }}</h1>
            <p class="text-sm text-ink-500">
                {{ $qaAssessment->period_name_snapshot }} &middot; oleh {{ $qaAssessment->qa_name_snapshot }}
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-badge :variant="$qaAssessment->status === 'published' ? 'green' : 'amber'">
                {{ $qaAssessment->status === 'published' ? 'Terbit' : 'Draft' }}
            </x-badge>

            @if ($qaAssessment->status === 'draft')
                @can('update', $qaAssessment)
                    <a href="{{ route('qa.assessments.edit', $qaAssessment) }}" class="btn-secondary btn-sm">Edit Catatan</a>
                @endcan
                @can('publish', $qaAssessment)
                    <form method="POST" action="{{ route('qa.assessments.publish', $qaAssessment) }}"
                          onsubmit="return confirm('Terbitkan assessment ini? Agent akan menerima notifikasi.')">
                        @csrf
                        <button type="submit" class="btn-primary btn-sm" @disabled($itemCount === 0)
                                title="{{ $itemCount === 0 ? 'Tambahkan minimal 1 item penilaian dulu sebelum menerbitkan.' : 'Terbitkan assessment ini' }}">
                            Terbitkan
                        </button>
                    </form>
                @endcan
            @endif

            @can('delete', $qaAssessment)
                <form method="POST" action="{{ route('qa.assessments.destroy', $qaAssessment) }}"
                      onsubmit="return confirm('Hapus assessment draft ini?')">
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
            <p class="text-xs uppercase tracking-wide text-ink-500">Agent</p>
            <p class="font-medium text-ink-900">{{ $qaAssessment->agent?->name ?? $qaAssessment->agent_name_snapshot }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-ink-500">QA</p>
            <p class="font-medium text-ink-900">{{ $qaAssessment->qa?->name ?? $qaAssessment->qa_name_snapshot }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-ink-500">Periode</p>
            <p class="font-medium text-ink-900">{{ $qaAssessment->period?->name ?? $qaAssessment->period_name_snapshot }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-ink-500">Diterbitkan</p>
            <p class="font-medium text-ink-900">
                {{ $qaAssessment->published_at?->format('d M Y H:i') ?? '—' }}
                @if ($qaAssessment->publishedBy)
                    <span class="text-xs font-normal text-ink-500">oleh {{ $qaAssessment->publishedBy->name }}</span>
                @endif
            </p>
        </div>
    </div>

    @if ($qaAssessment->general_notes)
        <div class="card card-body mb-5">
            <h2 class="text-sm font-semibold text-ink-900">Catatan umum</h2>
            <p class="mt-1 whitespace-pre-line text-sm text-ink-600">{{ $qaAssessment->general_notes }}</p>
        </div>
    @endif

    @include('qa.assessments._items')
@endsection
