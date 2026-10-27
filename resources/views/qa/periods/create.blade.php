@extends('layouts.app')

{{-- Tambah periode QA (PRD QA Assessment §7). --}}
{{-- AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, periode --}}

@section('title', 'Tambah Periode QA')

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-semibold text-ink-900">Tambah Periode</h1>
    </div>

    @include('qa.assessment._nav')

    <form method="POST" action="{{ route('qa.periods.store') }}" class="card card-body max-w-2xl space-y-5">
        @csrf
        @include('qa.periods._form', ['qaPeriod' => null])
        <div class="flex justify-end gap-2">
            <a class="btn-secondary" href="{{ route('qa.periods.index') }}">Batal</a>
            <button class="btn-primary">Simpan</button>
        </div>
    </form>
@endsection
