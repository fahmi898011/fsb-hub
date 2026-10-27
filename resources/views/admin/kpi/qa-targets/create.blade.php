@extends('layouts.app')

{{-- AGENT: asisten | 2026-10-04 | buat berkas baru | modul KPI, fase 3 QA reporting --}}

@section('title', 'Tambah Target Sampling')

@section('content')
    <div class="mb-5">
        <h1 class="text-xl font-semibold text-ink-900">Tambah Target Sampling</h1>
        <p class="text-sm text-ink-500">Contoh: 5 sample per karyawan per minggu untuk divisi Customer Service, scorecard Quality Inbound.</p>
    </div>

    <div class="card card-body max-w-3xl">
        <form method="POST" action="{{ route('admin.kpi.qa-targets.store') }}">
            @csrf
            @include('admin.kpi.qa-targets._form')
            <div class="mt-6 flex items-center gap-2">
                <button type="submit" class="btn-primary">Simpan</button>
                <a href="{{ route('admin.kpi.qa-targets.index') }}" class="btn-secondary">Batal</a>
            </div>
        </form>
    </div>
@endsection
