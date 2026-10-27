@extends('layouts.app')

@section('title', 'Tambah Tim')

@section('content')
    <div class="mb-5">
        <h1 class="text-xl font-semibold text-ink-900">Tambah Tim</h1>
        <p class="text-sm text-ink-500">Tentukan divisi, ketua, dan anggota tim.</p>
    </div>

    <div class="card card-body max-w-3xl">
        <form method="POST" action="{{ route('admin.teams.store') }}">
            @csrf

            @include('admin.teams._form')

            <div class="mt-6 flex items-center gap-2">
                <button type="submit" class="btn-primary">Simpan</button>
                <a href="{{ route('admin.teams.index') }}" class="btn-secondary">Batal</a>
            </div>
        </form>
    </div>
@endsection
