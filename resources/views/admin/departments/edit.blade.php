@extends('layouts.app')

@section('title', 'Edit Divisi')

@section('content')
    <div class="mb-5">
        <h1 class="text-xl font-semibold text-ink-900">Edit Divisi</h1>
        <p class="text-sm text-ink-500">
            {{ $department->name }} &middot; {{ $department->users_count }} karyawan,
            {{ $department->teams_count }} tim
        </p>
    </div>

    <div class="card card-body max-w-2xl">
        <form method="POST" action="{{ route('admin.departments.update', $department) }}">
            @csrf
            @method('PUT')

            @include('admin.departments._form')

            <div class="mt-6 flex items-center gap-2">
                <button type="submit" class="btn-primary">Simpan Perubahan</button>
                <a href="{{ route('admin.departments.index') }}" class="btn-secondary">Batal</a>
            </div>
        </form>
    </div>
@endsection
