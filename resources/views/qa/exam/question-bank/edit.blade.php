@extends('layouts.app')

{{-- Edit soal bank ujian (PRD QA Assessment §5). --}}
{{-- AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian B --}}

@section('title', 'Edit Soal Ujian')

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-semibold text-ink-900">Edit Soal</h1>
        <p class="text-sm text-ink-500">Perubahan tidak memengaruhi soal yang sudah di-snapshot ke form ujian.</p>
    </div>

    @include('qa.assessment._nav')

    <form method="POST" action="{{ route('qa.exam.question-bank.update', $questionBank) }}" class="card card-body max-w-3xl space-y-5">
        @csrf
        @method('PUT')
        @include('qa.exam.question-bank._form')
        <div class="flex justify-end gap-2">
            <a class="btn-secondary" href="{{ route('qa.exam.question-bank.show', $questionBank) }}">Batal</a>
            <button class="btn-primary">Simpan</button>
        </div>
    </form>
@endsection
