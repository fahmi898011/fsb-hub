@extends('layouts.app')

@section('title', 'Kelola Informasi')

@section('content')
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <x-search-bar :action="route('qa.information.index')" name="search" :value="$filters['search'] ?? null" placeholder="Cari informasi..." class="sm:max-w-sm" />

        <a href="{{ route('qa.information.create') }}" class="btn-primary shrink-0">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
            </svg>
            Tambah
        </a>
    </div>

    {{-- Filter status --}}
    <div class="mb-5 flex flex-wrap gap-2">
        @php
            $statuses = ['' => 'Semua', 'draft' => 'Draft', 'published' => 'Dipublikasikan', 'archived' => 'Diarsipkan'];
            $current = $filters['status'] ?? '';
        @endphp
        @foreach ($statuses as $value => $label)
            <a href="{{ route('qa.information.index', array_filter(['status' => $value, 'search' => $filters['search'] ?? null, 'category' => $filters['category'] ?? null])) }}"
               class="badge {{ $current === $value ? 'badge-brand' : 'badge-gray' }} px-3 py-1.5">{{ $label }}</a>
        @endforeach
    </div>

    @if ($items->isEmpty())
        <x-empty-state title="Belum ada informasi" description="Mulai dengan menambahkan informasi baru.">
            <a href="{{ route('qa.information.create') }}" class="btn-primary">Tambah Informasi</a>
        </x-empty-state>
    @else
        {{-- Tabel (desktop) --}}
        <div class="card hidden overflow-hidden lg:block">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-ink-100 bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Judul</th>
                        <th class="px-5 py-3 font-semibold">Kategori</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 font-semibold">Diperbarui</th>
                        <th class="px-5 py-3 font-semibold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @foreach ($items as $item)
                        <tr class="hover:bg-ink-50">
                            <td class="px-5 py-3">
                                <p class="max-w-xs truncate font-medium text-ink-800">{{ $item->title }}</p>
                                @if ($item->document_number)
                                    <p class="text-xs text-ink-400">{{ $item->document_number }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-ink-600">{{ $item->category?->name }}</td>
                            <td class="px-5 py-3"><x-status-badge :status="$item->status" /></td>
                            <td class="px-5 py-3 text-xs text-ink-400">{{ $item->updated_at?->translatedFormat('d M Y') }}</td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('qa.information.show', $item->slug) }}" class="btn-ghost btn-sm">Detail</a>
                                <a href="{{ route('qa.information.edit', $item->slug) }}" class="btn-secondary btn-sm">Edit</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Kartu (mobile) --}}
        <div class="space-y-3 lg:hidden">
            @foreach ($items as $item)
                <a href="{{ route('qa.information.show', $item->slug) }}" class="card card-body block">
                    <div class="flex items-start justify-between gap-3">
                        <p class="font-medium text-ink-900">{{ $item->title }}</p>
                        <x-status-badge :status="$item->status" />
                    </div>
                    <p class="mt-1 text-sm text-ink-500">{{ $item->category?->name }}</p>
                    <p class="mt-1 text-xs text-ink-400">Diperbarui {{ $item->updated_at?->diffForHumans() }}</p>
                </a>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $items->links() }}
        </div>
    @endif
@endsection