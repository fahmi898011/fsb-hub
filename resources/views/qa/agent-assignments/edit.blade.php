@extends('layouts.app')

{{-- Edit penugasan QA ke agent (PRD QA Assessment §6). --}}
{{-- AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian C --}}

@section('title', 'Edit Penugasan QA')

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-semibold text-ink-900">Edit Penugasan</h1>
    </div>

    @include('qa.assessment._nav')

    <form method="POST" action="{{ route('qa.agent-assignments.update', $qaAgentAssignment) }}" class="card card-body max-w-2xl space-y-5">
        @csrf
        @method('PUT')
        @include('qa.agent-assignments._form')
        <div class="flex justify-end gap-2">
            <a class="btn-secondary" href="{{ route('qa.agent-assignments.index') }}">Batal</a>
            <button class="btn-primary">Simpan</button>
        </div>
    </form>
@endsection
