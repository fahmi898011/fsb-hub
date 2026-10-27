@extends('layouts.app')

@section('title', 'Edit Tim')

@section('content')
    <div class="mb-5">
        <h1 class="text-xl font-semibold text-ink-900">Edit Tim</h1>
        <p class="text-sm text-ink-500">
            {{ $team->name }}
            @if ($team->department)
                &middot; {{ $team->department->name }}
            @endif
        </p>
    </div>

    <div class="card card-body max-w-3xl">
        <form method="POST" action="{{ route('admin.teams.update', $team) }}">
            @csrf
            @method('PUT')

            @include('admin.teams._form')

            <div class="mt-6 flex items-center gap-2">
                <button type="submit" class="btn-primary">Simpan Perubahan</button>
                <a href="{{ route('admin.teams.index') }}" class="btn-secondary">Batal</a>
            </div>
        </form>
    </div>
@endsection
