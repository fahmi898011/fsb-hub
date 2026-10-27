@extends('layouts.app')

@section('title', 'Semua Informasi')

@section('content')
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <x-search-bar :value="$filters['search'] ?? null" placeholder="Cari informasi..." class="sm:max-w-sm" />

        <form method="GET" class="flex items-center gap-2">
            @if (! empty($filters['search']))
                <input type="hidden" name="search" value="{{ $filters['search'] }}">
            @endif
            @if (! empty($filters['category']))
                <input type="hidden" name="category" value="{{ $filters['category'] }}">
            @endif
            <label for="sort" class="text-sm text-ink-500">Urutkan</label>
            <select id="sort" name="sort" onchange="this.form.submit()" class="form-input w-auto py-2 text-sm">
                <option value="latest" @selected(($filters['sort'] ?? 'latest') === 'latest')>Terbaru</option>
                <option value="popular" @selected(($filters['sort'] ?? '') === 'popular')>Terpopuler</option>
                <option value="az" @selected(($filters['sort'] ?? '') === 'az')>A–Z</option>
            </select>
        </form>
    </div>

    {{-- Filter kategori --}}
    <div class="mb-5 flex flex-wrap gap-2">
        <a href="{{ route('information.index', array_filter(['search' => $filters['search'] ?? null, 'sort' => $filters['sort'] ?? null])) }}"
           class="badge {{ empty($filters['category']) ? 'badge-brand' : 'badge-gray' }} px-3 py-1.5">Semua</a>
        @foreach ($categories as $category)
            <a href="{{ route('information.category', $category->slug) }}"
               class="badge {{ ($filters['category'] ?? null) === $category->slug ? 'badge-brand' : 'badge-gray' }} px-3 py-1.5">
                {{ $category->name }}
            </a>
        @endforeach
    </div>

    @if ($items->isEmpty())
        <x-empty-state title="Tidak ada informasi ditemukan" description="Coba ubah kata kunci atau filter kategori." />
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