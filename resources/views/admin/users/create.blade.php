@extends('layouts.app')

@section('title', 'Tambah Pengguna')

@section('content')
    <nav class="mb-4 flex items-center gap-2 text-sm text-ink-500">
        <a href="{{ route('admin.users.index') }}" class="hover:text-ink-800">Manajemen Pengguna</a>
        <span>/</span>
        <span class="text-ink-800">Tambah</span>
    </nav>

    <form method="POST" action="{{ route('admin.users.store') }}" class="card card-body max-w-3xl">
        @csrf
        @include('admin.users._form', ['user' => $user])

        <div class="mt-6 flex justify-end gap-2">
            <a href="{{ route('admin.users.index') }}" class="btn-secondary">Batal</a>
            <button type="submit" class="btn-primary">Simpan</button>
        </div>
    </form>
@endsection
