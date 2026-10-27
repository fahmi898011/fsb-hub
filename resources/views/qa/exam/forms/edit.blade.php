@extends('layouts.app')

{{-- Edit form ujian tertulis (PRD QA Assessment §5). --}}
{{-- AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian B --}}

@section('title', 'Edit Form Ujian')

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-semibold text-ink-900">Edit Form: {{ $examForm->title }}</h1>
        @if ($examForm->status !== 'draft')
            <p class="text-sm text-amber-600">Hanya form draft yang bisa diedit.</p>
        @endif
    </div>

    @include('qa.assessment._nav')

    <form method="POST" action="{{ route('qa.exam.forms.update', $examForm) }}" class="card card-body max-w-2xl space-y-5">
        @csrf
        @method('PUT')
        @include('qa.exam.forms._form')
        <div class="flex justify-end gap-2">
            <a class="btn-secondary" href="{{ route('qa.exam.forms.show', $examForm) }}">Batal</a>
            <button class="btn-primary" @disabled($examForm->status !== 'draft')>Simpan</button>
        </div>
    </form>
@endsection
