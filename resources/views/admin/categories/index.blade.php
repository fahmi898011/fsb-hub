@extends('layouts.app')

@section('title', 'Kategori Informasi')

@section('content')
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-ink-900">Master Informasi</h1>
            <p class="text-sm text-ink-500">Struktur kategori yang dipakai QA saat membuat informasi.</p>
        </div>

        <a href="{{ route('admin.categories.create') }}" class="btn-primary shrink-0">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
            </svg>
            Tambah Kategori
        </a>
    </div>

    @if ($categories->isEmpty())
        <x-empty-state title="Belum ada kategori" description="Tambahkan kategori pertama agar QA dapat membuat informasi.">
            <a href="{{ route('admin.categories.create') }}" class="btn-primary">Tambah Kategori</a>
        </x-empty-state>
    @else
        {{-- Tabel (desktop) --}}
        <div class="card hidden overflow-hidden lg:block">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-ink-100 bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Kategori</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 font-semibold">Informasi</th>
                        <th class="px-5 py-3 font-semibold">Urutan</th>
                        <th class="px-5 py-3 font-semibold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @foreach ($categories as $category)
                        <tr class="hover:bg-ink-50">
                            <td class="px-5 py-3">
                                <p class="font-medium text-ink-800">{{ $category->name }}</p>
                                <p class="text-xs text-ink-400">{{ $category->slug }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <x-badge :variant="$category->status === 'active' ? 'green' : 'gray'">
                                    {{ $category->status === 'active' ? 'Aktif' : 'Nonaktif' }}
                                </x-badge>
                            </td>
                            <td class="px-5 py-3 text-ink-600">{{ $category->items_count }}</td>
                            <td class="px-5 py-3 text-ink-600">{{ $category->sort_order }}</td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('admin.categories.edit', $category->slug) }}" class="btn-secondary btn-sm">Edit</a>

                                <form method="POST" action="{{ route('admin.categories.destroy', $category->slug) }}" class="inline"
                                      onsubmit="return confirm('Hapus kategori ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-ghost btn-sm text-red-600 hover:bg-red-50">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Kartu (mobile) --}}
        <div class="space-y-3 lg:hidden">
            @foreach ($categories as $category)
                <div class="card card-body">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-ink-900">{{ $category->name }}</p>
                            <p class="text-xs text-ink-400">{{ $category->items_count }} informasi &middot; urutan {{ $category->sort_order }}</p>
                        </div>
                        <x-badge :variant="$category->status === 'active' ? 'green' : 'gray'">
                            {{ $category->status === 'active' ? 'Aktif' : 'Nonaktif' }}
                        </x-badge>
                    </div>

                    <div class="mt-3 flex gap-2">
                        <a href="{{ route('admin.categories.edit', $category->slug) }}" class="btn-secondary btn-sm">Edit</a>

                        <form method="POST" action="{{ route('admin.categories.destroy', $category->slug) }}"
                              onsubmit="return confirm('Hapus kategori ini?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-ghost btn-sm text-red-600 hover:bg-red-50">Hapus</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
