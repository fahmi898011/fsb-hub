@extends('layouts.app')

@section('title', 'Tambah Divisi')

@section('content')
    <div class="mb-5">
        <h1 class="text-xl font-semibold text-ink-900">Tambah Divisi</h1>
        <p class="text-sm text-ink-500">Divisi baru langsung dapat dipakai untuk menautkan karyawan.</p>
    </div>

    <div class="card card-body max-w-2xl">
        <form method="POST" action="{{ route('admin.departments.store') }}">
            @csrf

            @include('admin.departments._form')

            <div class="mt-6 flex items-center gap-2">
                <button type="submit" class="btn-primary">Simpan</button>
                <a href="{{ route('admin.departments.index') }}" class="btn-secondary">Batal</a>
            </div>
        </form>
    </div>
@endsection
