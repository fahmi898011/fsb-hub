@extends('layouts.app')

{{-- Edit catatan umum penilaian kualitatif (PRD QA Assessment §4). --}}
{{-- AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian A --}}

@section('title', 'Edit Penilaian QA')

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-semibold text-ink-900">Edit Penilaian: {{ $qaAssessment->agent_name_snapshot }}</h1>
        <p class="text-sm text-ink-500">{{ $qaAssessment->period_name_snapshot }}</p>
    </div>

    @include('qa.assessment._nav')

    <form method="POST" action="{{ route('qa.assessments.update', $qaAssessment) }}" class="card card-body mb-5 max-w-2xl space-y-4">
        @csrf
        @method('PUT')

        <div>
            <label for="general_notes" class="form-label">Catatan umum</label>
            <textarea id="general_notes" name="general_notes" rows="4" class="form-input w-full">{{ old('general_notes', $qaAssessment->general_notes) }}</textarea>
            @error('general_notes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('qa.assessments.show', $qaAssessment) }}" class="btn-secondary">Batal</a>
            <button type="submit" class="btn-primary">Simpan</button>
        </div>
    </form>

    @include('qa.assessments._items')
@endsection
