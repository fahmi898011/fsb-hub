@extends('layouts.app')

{{-- AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master --}}

@section('title', 'Edit Channel KPI')

@section('content')
    <div class="mb-5">
        <h1 class="text-xl font-semibold text-ink-900">Edit Channel KPI</h1>
        <p class="text-sm text-ink-500">{{ $channel->name }} &middot; {{ $channel->scorecards_count }} scorecard</p>
    </div>

    <div class="card card-body max-w-2xl">
        <form method="POST" action="{{ route('admin.kpi.channels.update', $channel) }}">
            @csrf
            @method('PUT')
            @include('admin.kpi.channels._form')
            <div class="mt-6 flex items-center gap-2">
                <button type="submit" class="btn-primary">Simpan Perubahan</button>
                <a href="{{ route('admin.kpi.channels.index') }}" class="btn-secondary">Batal</a>
            </div>
        </form>
    </div>
@endsection
