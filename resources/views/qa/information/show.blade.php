@extends('layouts.app')

@section('title', $item->title)

@section('content')
    <nav class="mb-4 flex flex-wrap items-center gap-2 text-sm text-ink-500">
        <a href="{{ route('qa.dashboard') }}" class="hover:text-ink-800">Dashboard QA</a>
        <span>/</span>
        <a href="{{ route('qa.information.index') }}" class="hover:text-ink-800">Kelola Informasi</a>
        <span>/</span>
        <span class="truncate text-ink-800">{{ $item->title }}</span>
    </nav>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Kolom utama --}}
        <div class="lg:col-span-2">
            <div class="card card-body">
                <div class="flex flex-wrap items-center gap-2">
                    @if ($item->category)
                        <x-badge variant="brand">{{ $item->category->name }}</x-badge>
                    @endif
                    <x-status-badge :status="$item->status" />
                    @if ($item->document_number)
                        <span class="text-xs text-ink-400">{{ $item->document_number }}</span>
                    @endif
                </div>

                <h1 class="mt-3 text-xl font-semibold text-ink-900">{{ $item->title }}</h1>

                @if ($item->description)
                    <p class="mt-2 text-sm text-ink-600">{{ $item->description }}</p>
                @endif

                @if ($item->tags->isNotEmpty())
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($item->tags as $tag)
                            <x-badge variant="gray">#{{ $tag->name }}</x-badge>
                        @endforeach
                    </div>
                @endif

                <div class="mt-5 flex flex-wrap gap-2">
                    <a href="{{ route('qa.information.edit', $item->slug) }}" class="btn-secondary">Edit</a>
                    <a href="{{ route('information.show', $item->slug) }}" target="_blank" rel="noopener" class="btn-ghost">Lihat sebagai karyawan</a>

                    @can('publish', $item)
                        @if ($item->status !== 'published')
                            <form method="POST" action="{{ route('qa.information.publish', $item->slug) }}">
                                @csrf
                                <button type="submit" class="btn-primary">Publikasikan</button>
                            </form>
                        @endif
                    @endcan

                    @can('archive', $item)
                        @if ($item->status !== 'archived')
                            <form method="POST" action="{{ route('qa.information.archive', $item->slug) }}">
                                @csrf
                                <button type="submit" class="btn-ghost text-amber-600 hover:bg-amber-50">Arsipkan</button>
                            </form>
                        @endif
                    @endcan
                </div>
            </div>

            {{-- Riwayat versi --}}
            <div class="card mt-4">
                <div class="border-b border-ink-100 px-5 py-3">
                    <h2 class="text-sm font-semibold text-ink-900">Riwayat Versi</h2>
                </div>
                <div class="divide-y divide-ink-100">
                    @forelse ($item->versions as $version)
                        <div class="flex items-start gap-3 px-5 py-3">
                            <span class="badge-brand">v{{ $version->version }}</span>
                            <div class="min-w-0">
                                <p class="text-sm text-ink-700">{{ $version->change_notes ?: 'Tanpa catatan perubahan.' }}</p>
                                <p class="mt-0.5 text-xs text-ink-400">
                                    {{ $version->file_size_human }} · {{ $version->created_at?->translatedFormat('d M Y') }}
                                    @if ($version->creator) · {{ $version->creator->name }}@endif
                                </p>
                            </div>
                            @if ($item->current_version_id === $version->id)
                                <x-badge variant="green" class="ml-auto shrink-0">Aktif</x-badge>
                            @endif
                        </div>
                    @empty
                        <p class="px-5 py-4 text-sm text-ink-400">Belum ada versi dokumen.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Sidebar: tambah versi --}}
        <aside>
            @can('version', $item)
                <div class="card card-body">
                    <h2 class="text-sm font-semibold text-ink-900">Tambah Versi Baru</h2>

                    <form method="POST" action="{{ route('qa.information.version', $item->slug) }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                        @csrf

                        <div>
                            <label for="document" class="form-label">File PDF <span class="text-red-500">*</span></label>
                            <input id="document" name="document" type="file" accept="application/pdf" required class="form-input file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-brand-700">
                            @error('document')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="version" class="form-label">Nomor Versi <span class="text-red-500">*</span></label>
                            <input id="version" name="version" type="text" value="{{ old('version') }}" required class="form-input" placeholder="mis. 1.1">
                            @error('version')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="change_notes" class="form-label">Catatan Perubahan</label>
                            <textarea id="change_notes" name="change_notes" rows="3" class="form-input">{{ old('change_notes') }}</textarea>
                        </div>

                        <button type="submit" class="btn-primary w-full">Unggah Versi</button>
                    </form>
                </div>
            @endcan
        </aside>
    </div>
@endsection