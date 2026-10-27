@extends('layouts.app')

{{-- AGENT: asisten | 2026-10-04 | buat berkas baru | modul KPI, fase 3 QA reporting --}}

@section('title', 'Edit Target Sampling')

@section('content')
    <div class="mb-5">
        <h1 class="text-xl font-semibold text-ink-900">Edit Target Sampling</h1>
        <p class="text-sm text-ink-500">
            Perubahan berlaku untuk laporan yang dihitung SETELAH tanggal efektif ini — laporan periode lampau
            tetap memakai aturan yang berlaku saat itu (PRD §11/§38).
        </p>
    </div>

    <div class="card card-body max-w-3xl">
        <form method="POST" action="{{ route('admin.kpi.qa-targets.update', $target) }}">
            @csrf
            @method('PUT')
            @include('admin.kpi.qa-targets._form')
            <div class="mt-6 flex items-center gap-2">
                <button type="submit" class="btn-primary">Simpan Perubahan</button>
                <a href="{{ route('admin.kpi.qa-targets.index') }}" class="btn-secondary">Batal</a>
            </div>
        </form>
    </div>
@endsection
