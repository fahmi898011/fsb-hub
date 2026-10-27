@extends('layouts.app')

{{-- AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master --}}

@section('title', 'Tambah Channel KPI')

@section('content')
    <div class="mb-5">
        <h1 class="text-xl font-semibold text-ink-900">Tambah Channel KPI</h1>
    </div>

    <div class="card card-body max-w-2xl">
        <form method="POST" action="{{ route('admin.kpi.channels.store') }}">
            @csrf
            @include('admin.kpi.channels._form')
            <div class="mt-6 flex items-center gap-2">
                <button type="submit" class="btn-primary">Simpan</button>
                <a href="{{ route('admin.kpi.channels.index') }}" class="btn-secondary">Batal</a>
            </div>
        </form>
    </div>
@endsection
