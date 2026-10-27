@extends('layouts.app')

{{-- Tambah kategori penilaian (PRD QA Assessment §4). --}}
{{-- AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian A --}}

@section('title', 'Tambah Kategori Penilaian')

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-semibold text-ink-900">Tambah Kategori</h1>
        <p class="text-sm text-ink-500">Kategori dipakai saat menulis penilaian dan mengelompokkan bank soal.</p>
    </div>

    @include('qa.assessment._nav')

    <form method="POST" action="{{ route('qa.assessment.categories.store') }}" class="card card-body max-w-2xl space-y-5">
        @csrf
        @include('qa.assessment.categories._form', ['qaAssessmentCategory' => null])
        <div class="flex justify-end gap-2">
            <a class="btn-secondary" href="{{ route('qa.assessment.categories.index') }}">Batal</a>
            <button class="btn-primary">Simpan</button>
        </div>
    </form>
@endsection
