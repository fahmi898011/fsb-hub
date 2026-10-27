@extends('layouts.app')

@section('title', 'Tambah Role')

@section('content')
    <div class="mb-5">
        <a href="{{ route('admin.roles.index') }}" class="text-sm text-ink-500 hover:text-ink-800">&larr; Kembali ke daftar role</a>
        <h1 class="mt-1 text-xl font-semibold text-ink-900">Tambah Role Baru</h1>
        <p class="text-sm text-ink-500">Buat role kustom di luar 7 role bawaan aplikasi.</p>
    </div>

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3">
            @foreach ($errors->all() as $error)
                <p class="text-sm text-red-800">{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('admin.roles.store') }}">
        @csrf

        <div class="card card-body mb-5">
            <label for="name" class="form-label">Nama Role <span class="text-red-500">*</span></label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required
                   class="form-input sm:max-w-sm" placeholder="mis. auditor">
            <p class="mt-1.5 text-xs text-ink-400">Huruf kecil, angka, dan garis bawah. Contoh: <span class="font-mono">auditor</span>, <span class="font-mono">finance_staff</span>.</p>
            @error('name')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <h2 class="mb-3 text-sm font-semibold text-ink-900">Permission</h2>
        @include('admin.roles._permissions', ['groups' => $groups])

        <div class="mt-5 flex flex-wrap gap-2">
            <button type="submit" class="btn-primary">Simpan Role</button>
            <a href="{{ route('admin.roles.index') }}" class="btn-secondary">Batal</a>
        </div>
    </form>
@endsection
