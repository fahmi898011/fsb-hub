@extends('layouts.app')

@section('title', 'Kelola Tag')

@section('content')
    <div class="mb-5">
        <h1 class="text-xl font-semibold text-ink-900">Kelola Tag</h1>
        <p class="text-sm text-ink-500">Tag dipakai untuk memperkaya pencarian informasi.</p>
    </div>

    <div class="mb-5">
        <x-search-bar :action="route('qa.tags.index')" name="search" :value="$filters['search'] ?? null" placeholder="Cari tag..." class="sm:max-w-sm" />
    </div>

    @if ($tags->isEmpty())
        <x-empty-state
            title="Belum ada tag"
            description="Tag dibuat otomatis saat QA menambahkan kata kunci pada form informasi."
        />
    @else
        <div class="card hidden overflow-hidden lg:block">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-ink-100 bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Nama</th>
                        <th class="px-5 py-3 font-semibold">Slug</th>
                        <th class="px-5 py-3 font-semibold">Dipakai</th>
                        <th class="px-5 py-3 text-right font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @foreach ($tags as $tag)
                        <tr class="hover:bg-ink-50">
                            <td class="px-5 py-3 font-medium text-ink-900">{{ $tag->name }}</td>
                            <td class="px-5 py-3 font-mono text-xs text-ink-500">{{ $tag->slug }}</td>
                            <td class="px-5 py-3">
                                <x-badge :variant="$tag->information_count > 0 ? 'brand' : 'gray'">
                                    {{ $tag->information_count }} informasi
                                </x-badge>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('qa.tags.edit', $tag) }}" class="btn-secondary btn-sm">Edit</a>

                                    <form method="POST" action="{{ route('qa.tags.destroy', $tag) }}"
                                          onsubmit="return confirm('Hapus tag {{ $tag->name }}?{{ $tag->information_count > 0 ? ' Tag akan dilepas dari ' . $tag->information_count . ' informasi.' : '' }}');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-danger btn-sm">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Kartu (mobile) --}}
        <div class="space-y-3 lg:hidden">
            @foreach ($tags as $tag)
                <div class="card card-body">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-ink-900">{{ $tag->name }}</p>
                            <p class="truncate font-mono text-xs text-ink-400">{{ $tag->slug }}</p>
                        </div>
                        <x-badge :variant="$tag->information_count > 0 ? 'brand' : 'gray'">
                            {{ $tag->information_count }}
                        </x-badge>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <a href="{{ route('qa.tags.edit', $tag) }}" class="btn-secondary btn-sm">Edit</a>

                        <form method="POST" action="{{ route('qa.tags.destroy', $tag) }}"
                              onsubmit="return confirm('Hapus tag {{ $tag->name }}?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-danger btn-sm">Hapus</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-5">
            {{ $tags->links() }}
        </div>
    @endif
@endsection
