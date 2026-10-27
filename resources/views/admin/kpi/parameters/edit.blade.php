@extends('layouts.app')

{{-- AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master --}}

@section('title', 'Edit Parameter')

@section('content')
    <div class="mb-5">
        <h1 class="text-xl font-semibold text-ink-900">Edit Parameter</h1>
        <p class="text-sm text-ink-500">{{ $parameter->name }} &middot; {{ $parameter->category->name }}</p>
    </div>

    <div class="card card-body max-w-3xl">
        <form method="POST" action="{{ route('admin.kpi.parameters.update', $parameter) }}">
            @csrf
            @method('PUT')
            @include('admin.kpi.parameters._form')
            <div class="mt-6 flex items-center gap-2">
                <button type="submit" class="btn-primary">Simpan Perubahan</button>
                <a href="{{ route('admin.kpi.versions.show', $parameter->category->version) }}" class="btn-secondary">Batal</a>
            </div>
        </form>
    </div>
@endsection
