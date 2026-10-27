@extends('layouts.app')

@section('title', 'Edit Informasi')

@section('content')
    <nav class="mb-4 flex items-center gap-2 text-sm text-ink-500">
        <a href="{{ route('qa.dashboard') }}" class="hover:text-ink-800">Dashboard QA</a>
        <span>/</span>
        <a href="{{ route('qa.information.index') }}" class="hover:text-ink-800">Kelola Informasi</a>
        <span>/</span>
        <span class="text-ink-800">Edit</span>
    </nav>

    <form method="POST" action="{{ route('qa.information.update', $item->slug) }}" enctype="multipart/form-data" class="card card-body max-w-3xl">
        @csrf
        @method('PUT')
        @include('qa.information._form', ['item' => $item])

        <div class="mt-6 flex justify-end gap-2">
            <a href="{{ route('qa.information.show', $item->slug) }}" class="btn-secondary">Batal</a>
            <button type="submit" class="btn-primary">Perbarui</button>
        </div>
    </form>
@endsection