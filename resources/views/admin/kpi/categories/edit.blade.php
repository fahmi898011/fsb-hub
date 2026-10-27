@extends('layouts.app')

{{-- AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master --}}

@section('title', 'Edit Kategori')

@section('content')
    <div class="mb-5">
        <h1 class="text-xl font-semibold text-ink-900">Edit Kategori</h1>
        <p class="text-sm text-ink-500">{{ $category->name }}</p>
    </div>

    <div class="card card-body max-w-2xl">
        <form method="POST" action="{{ route('admin.kpi.categories.update', $category) }}">
            @csrf
            @method('PUT')
            @include('admin.kpi.categories._form')
            <div class="mt-6 flex items-center gap-2">
                <button type="submit" class="btn-primary">Simpan Perubahan</button>
                <a href="{{ route('admin.kpi.versions.show', $category->version) }}" class="btn-secondary">Batal</a>
            </div>
        </form>
    </div>
@endsection
