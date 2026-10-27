@extends('layouts.app')

@section('title', 'Atur Permission')

@section('content')
    <div class="mb-5">
        <a href="{{ route('admin.roles.index') }}" class="text-sm text-ink-500 hover:text-ink-800">&larr; Kembali ke daftar role</a>
        <h1 class="mt-1 text-xl font-semibold text-ink-900">Atur Permission: {{ \App\Models\User::roleLabelFor($role->name) }}</h1>
        <p class="text-sm text-ink-500">
            Centang hak akses untuk role <span class="font-medium">{{ $role->name }}</span>.
            Dipakai oleh {{ $role->users()->count() }} pengguna.
        </p>
    </div>

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3">
            @foreach ($errors->all() as $error)
                <p class="text-sm text-red-800">{{ $error }}</p>
            @endforeach
        </div>
    @endif

    @if ($role->name === 'admin')
        <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
            <p class="text-sm text-amber-900">
                Role <span class="font-semibold">admin</span> wajib mempertahankan permission
                <span class="font-mono text-xs">{{ implode(', ', \App\Policies\RolePolicy::ADMIN_REQUIRED_PERMISSIONS) }}</span>
                agar tidak terkunci dari aplikasi.
            </p>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.roles.update', $role) }}">
        @csrf
        @method('PUT')

        @include('admin.roles._permissions', ['role' => $role, 'groups' => $groups])

        <div class="mt-5 flex flex-wrap gap-2">
            <button type="submit" class="btn-primary">Simpan Perubahan</button>
            <a href="{{ route('admin.roles.index') }}" class="btn-secondary">Batal</a>
        </div>
    </form>
@endsection
