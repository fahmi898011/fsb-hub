@extends('layouts.app')

@section('title', 'Tambah Informasi')

@section('content')
    <nav class="mb-4 flex items-center gap-2 text-sm text-ink-500">
        <a href="{{ route('qa.dashboard') }}" class="hover:text-ink-800">Dashboard QA</a>
        <span>/</span>
        <a href="{{ route('qa.information.index') }}" class="hover:text-ink-800">Kelola Informasi</a>
        <span>/</span>
        <span class="text-ink-800">Tambah</span>
    </nav>

    <form method="POST" action="{{ route('qa.information.store') }}" enctype="multipart/form-data" class="card card-body max-w-3xl">
        @csrf
        @include('qa.information._form', ['item' => null])

        <div class="mt-6 flex justify-end gap-2">
            <a href="{{ route('qa.information.index') }}" class="btn-secondary">Batal</a>
            <button type="submit" class="btn-primary">Simpan</button>
        </div>
    </form>
@endsection