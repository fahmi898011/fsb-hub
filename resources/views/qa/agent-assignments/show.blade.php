@extends('layouts.app')

{{-- Detail penugasan QA ke agent (PRD QA Assessment §6). --}}
{{-- AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian C --}}

@section('title', 'Detail Penugasan QA')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-ink-900">{{ $qaAgentAssignment->qa?->name }} &rarr; {{ $qaAgentAssignment->agent?->name }}</h1>
        <div class="flex items-center gap-2">
            <x-badge :variant="$qaAgentAssignment->active ? 'green' : 'gray'">
                {{ $qaAgentAssignment->active ? 'Aktif' : 'Nonaktif' }}
            </x-badge>
            @can('update', $qaAgentAssignment)
                <a href="{{ route('qa.agent-assignments.edit', $qaAgentAssignment) }}" class="btn-secondary btn-sm">Ubah</a>
            @endcan
            @can('delete', $qaAgentAssignment)
                <form method="POST" action="{{ route('qa.agent-assignments.destroy', $qaAgentAssignment) }}"
                      onsubmit="return confirm('Hapus penugasan ini?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-secondary btn-sm text-red-600">Hapus</button>
                </form>
            @endcan
        </div>
    </div>

    @include('qa.assessment._nav')

    <div class="card card-body mb-5 grid gap-4 text-sm sm:grid-cols-2">
        <div>
            <p class="text-xs uppercase tracking-wide text-ink-500">Ditetapkan oleh</p>
            <p class="font-medium text-ink-900">{{ $qaAgentAssignment->assignedBy?->name ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-ink-500">Catatan</p>
            <p class="font-medium text-ink-900">{{ $qaAgentAssignment->notes ?: '—' }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-ink-500">Dibuat</p>
            <p class="font-medium text-ink-900">{{ $qaAgentAssignment->created_at->format('d M Y H:i') }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-ink-500">Diperbarui</p>
            <p class="font-medium text-ink-900">
                {{ $qaAgentAssignment->updated_at->format('d M Y H:i') }}
                @if ($qaAgentAssignment->updatedBy)
                    <span class="text-xs font-normal text-ink-500">oleh {{ $qaAgentAssignment->updatedBy->name }}</span>
                @endif
            </p>
        </div>
    </div>

    <div>
        <a href="{{ route('qa.agent-assignments.index') }}" class="btn-secondary">Kembali</a>
    </div>
@endsection
