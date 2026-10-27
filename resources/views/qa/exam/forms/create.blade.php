@extends('layouts.app')

{{-- Buat form ujian tertulis (PRD QA Assessment §5). --}}
{{-- AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian B --}}

@section('title', 'Buat Form Ujian')

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-semibold text-ink-900">Buat Form Ujian</h1>
        <p class="text-sm text-ink-500">Isi pengaturan dulu; soal dan peserta diatur di halaman detail.</p>
    </div>

    @include('qa.assessment._nav')

    <form method="POST" action="{{ route('qa.exam.forms.store') }}" class="card card-body max-w-2xl space-y-5">
        @csrf
        @include('qa.exam.forms._form')
        <div class="flex justify-end gap-2">
            <a class="btn-secondary" href="{{ route('qa.exam.forms.index') }}">Batal</a>
            <button class="btn-primary">Simpan</button>
        </div>
    </form>
@endsection
