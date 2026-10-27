@extends('layouts.app')

{{-- Detail kategori penilaian (PRD QA Assessment §4). --}}
{{-- AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian A --}}

@section('title', 'Detail Kategori Penilaian')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-ink-900">{{ $qaAssessmentCategory->name }}</h1>
        <div class="flex items-center gap-2">
            <x-badge :variant="$qaAssessmentCategory->status === 'active' ? 'green' : 'gray'">
                {{ $qaAssessmentCategory->status === 'active' ? 'Aktif' : 'Nonaktif' }}
            </x-badge>
            @can('update', $qaAssessmentCategory)
                <a href="{{ route('qa.assessment.categories.edit', $qaAssessmentCategory) }}" class="btn-secondary btn-sm">Ubah</a>
            @endcan
            @can('delete', $qaAssessmentCategory)
                <form method="POST" action="{{ route('qa.assessment.categories.destroy', $qaAssessmentCategory) }}"
                      onsubmit="return confirm('Hapus kategori ini?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-secondary btn-sm text-red-600">Hapus</button>
                </form>
            @endcan
        </div>
    </div>

    @include('qa.assessment._nav')

    <div class="card card-body mb-5 grid gap-4 text-sm sm:grid-cols-3">
        <div>
            <p class="text-xs uppercase tracking-wide text-ink-500">Deskripsi</p>
            <p class="font-medium text-ink-900">{{ $qaAssessmentCategory->description ?: '—' }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-ink-500">Urutan</p>
            <p class="font-medium text-ink-900">{{ $qaAssessmentCategory->sort_order }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-ink-500">Dipakai pada item penilaian</p>
            <p class="font-medium text-ink-900">{{ number_format($qaAssessmentCategory->assessments_count) }} item
                <span class="text-xs font-normal text-ink-500">({{ number_format($qaAssessmentCategory->published_assessments_count) }} terbit)</span>
            </p>
        </div>
    </div>

    <div class="flex justify-between">
        <a href="{{ route('qa.assessment.categories.index') }}" class="btn-secondary">Kembali</a>
    </div>
@endsection
