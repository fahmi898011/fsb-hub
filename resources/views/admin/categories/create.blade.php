@extends('layouts.app')

@section('title', 'Tambah Kategori')

@section('content')
    <nav class="mb-4 flex items-center gap-2 text-sm text-ink-500">
        <a href="{{ route('admin.categories.index') }}" class="hover:text-ink-800">Master Informasi</a>
        <span>/</span>
        <span class="text-ink-800">Tambah</span>
    </nav>

    <form method="POST" action="{{ route('admin.categories.store') }}" class="card card-body max-w-3xl">
        @csrf
        @include('admin.categories._form', ['category' => $category])

        <div class="mt-6 flex justify-end gap-2">
            <a href="{{ route('admin.categories.index') }}" class="btn-secondary">Batal</a>
            <button type="submit" class="btn-primary">Simpan</button>
        </div>
    </form>
@endsection
