@extends('layouts.app')

@section('title', 'Edit Tag')

@section('content')
    <div class="mb-5">
        <a href="{{ route('qa.tags.index') }}" class="text-sm text-ink-500 hover:text-ink-800">&larr; Kembali ke daftar tag</a>
        <h1 class="mt-1 text-xl font-semibold text-ink-900">Edit Tag</h1>
        <p class="text-sm text-ink-500">Dipakai oleh {{ $tag->information_count }} informasi.</p>
    </div>

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3">
            @foreach ($errors->all() as $error)
                <p class="text-sm text-red-800">{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('qa.tags.update', $tag) }}">
        @csrf
        @method('PUT')

        <div class="card card-body">
            <div class="sm:max-w-md">
                <label for="name" class="form-label">Nama Tag <span class="text-red-500">*</span></label>
                <input id="name" name="name" type="text" value="{{ old('name', $tag->name) }}" required class="form-input">
                <p class="mt-1.5 text-xs text-ink-400">
                    Slug saat ini: <span class="font-mono">{{ $tag->slug }}</span>.
                    Slug akan menyesuaikan otomatis bila nama diubah.
                </p>
                @error('name')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="mt-5 flex flex-wrap gap-2 border-t border-ink-100 pt-4">
                <button type="submit" class="btn-primary">Simpan</button>
                <a href="{{ route('qa.tags.index') }}" class="btn-secondary">Batal</a>
            </div>
        </div>
    </form>
@endsection
