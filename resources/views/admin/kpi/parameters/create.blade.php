@extends('layouts.app')

{{-- AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master --}}

@section('title', 'Tambah Parameter')

@section('content')
    <div class="mb-5">
        <h1 class="text-xl font-semibold text-ink-900">Tambah Parameter</h1>
        <p class="text-sm text-ink-500">{{ $category->name }} &middot; {{ $category->version->scorecard->name }}</p>
    </div>

    <div class="card card-body max-w-3xl">
        <form method="POST" action="{{ route('admin.kpi.parameters.store', $category) }}">
            @csrf
            @include('admin.kpi.parameters._form')
            <div class="mt-6 flex items-center gap-2">
                <button type="submit" class="btn-primary">Simpan</button>
                <a href="{{ route('admin.kpi.versions.show', $category->version) }}" class="btn-secondary">Batal</a>
            </div>
        </form>
    </div>
@endsection
