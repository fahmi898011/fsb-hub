@extends('layouts.app')

@section('title', 'Edit Pengguna')

@section('content')
    <nav class="mb-4 flex items-center gap-2 text-sm text-ink-500">
        <a href="{{ route('admin.users.index') }}" class="hover:text-ink-800">Manajemen Pengguna</a>
        <span>/</span>
        <span class="text-ink-800">Edit</span>
    </nav>

    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="card card-body max-w-3xl">
        @csrf
        @method('PUT')
        @include('admin.users._form', ['user' => $user])

        <div class="mt-6 flex justify-end gap-2">
            <a href="{{ route('admin.users.index') }}" class="btn-secondary">Batal</a>
            <button type="submit" class="btn-primary">Perbarui</button>
        </div>
    </form>
@endsection
