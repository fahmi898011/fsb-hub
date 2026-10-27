@extends('layouts.app')

{{-- Edit kategori penilaian (PRD QA Assessment §4). --}}
{{-- AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian A --}}

@section('title', 'Edit Kategori Penilaian')

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-semibold text-ink-900">Edit Kategori: {{ $qaAssessmentCategory->name }}</h1>
    </div>

    @include('qa.assessment._nav')

    <form method="POST" action="{{ route('qa.assessment.categories.update', $qaAssessmentCategory) }}" class="card card-body max-w-2xl space-y-5">
        @csrf
        @method('PUT')
        @include('qa.assessment.categories._form')
        <div class="flex justify-end gap-2">
            <a class="btn-secondary" href="{{ route('qa.assessment.categories.index') }}">Batal</a>
            <button class="btn-primary">Simpan</button>
        </div>
    </form>
@endsection
