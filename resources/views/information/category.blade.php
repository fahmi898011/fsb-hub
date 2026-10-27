@extends('layouts.app')

@section('title', $category->name)

@section('content')
    <nav class="mb-4 flex items-center gap-2 text-sm text-ink-500">
        <a href="{{ route('home') }}" class="hover:text-ink-800">Beranda</a>
        <span>/</span>
        <a href="{{ route('information.index') }}" class="hover:text-ink-800">Informasi</a>
        <span>/</span>
        <span class="text-ink-800">{{ $category->name }}</span>
    </nav>

    <div class="card mb-5 p-5 sm:p-6">
        <div class="flex items-start gap-4">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl"
                  style="background-color: {{ ($category->color ?: '#3b82f6') }}14; color: {{ $category->color ?: '#3b82f6' }};">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16M4 12h16M4 19h10" />
                </svg>
            </span>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-lg font-semibold text-ink-900">{{ $category->name }}</h1>
                    <span class="badge badge-gray">{{ $items->total() }} dokumen</span>
                </div>
                @if ($category->description)
                    <p class="mt-1 text-sm leading-relaxed text-ink-500">{{ $category->description }}</p>
                @endif
            </div>
        </div>

        <div class="mt-5 max-w-md">
            <x-search-bar :action="route('information.category', $category->slug)" name="search" :value="$filters['search'] ?? null" placeholder="Cari di kategori ini..." />
        </div>
    </div>

    @if ($items->isEmpty())
        <x-empty-state title="Belum ada informasi" description="Kategori ini masih kosong atau tidak ada hasil yang cocok." />
    @else
        <div class="doc-list">
            @foreach ($items as $item)
                <x-information-card :item="$item" />
            @endforeach
        </div>

        <div class="mt-6">
            {{ $items->links() }}
        </div>
    @endif
@endsection