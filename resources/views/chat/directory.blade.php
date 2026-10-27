@extends('layouts.app')

@section('title', 'Daftar Karyawan')

@section('content')
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-ink-900">Daftar Karyawan</h1>
            <p class="text-sm text-ink-500">Mulai percakapan pribadi dengan rekan kerja dari divisi mana pun.</p>
        </div>

        <a href="{{ route('chat.index') }}" class="btn-secondary shrink-0">Kembali ke Chat</a>
    </div>

    <form method="GET" action="{{ route('chat.directory') }}" class="card card-body mb-4">
        <label for="search" class="form-label">Cari karyawan</label>
        <div class="flex gap-2">
            <input id="search" name="search" type="search" value="{{ $term }}" class="form-input"
                   placeholder="Nama, ID karyawan, atau divisi">
            <button type="submit" class="btn-secondary shrink-0">Cari</button>
            @if ($term !== '')
                <a href="{{ route('chat.directory') }}" class="btn-ghost shrink-0">Reset</a>
            @endif
        </div>
    </form>

    @if ($users->isEmpty())
        <x-empty-state
            title="Tidak ada karyawan ditemukan"
            description="Coba ubah kata kunci, atau pastikan akun karyawan yang dicari berstatus aktif."
        />
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($users as $user)
                @php($existingId = $directByUser[$user->id] ?? null)

                <div class="card card-body flex items-start gap-3">
                    <img src="{{ $user->avatar_url }}" alt="" class="h-11 w-11 shrink-0 rounded-full object-cover ring-1 ring-ink-200">

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-ink-900">{{ $user->name }}</p>
                        <p class="truncate text-xs text-ink-500">{{ $user->departmentName() ?: 'Tanpa divisi' }}</p>
                        <p class="mt-0.5 truncate text-xs text-ink-400">{{ $user->role_label }}</p>

                        {{-- DM yang sudah ada langsung dibuka (tidak membuat
                             percakapan baru), sedangkan yang belum dibuat
                             dikirim lewat POST agar prefetch tidak membuat
                             percakapan tanpa sengaja. --}}
                        @if ($existingId)
                            <a href="{{ route('chat.show', $existingId) }}" class="btn-secondary btn-sm mt-3">Buka Percakapan</a>
                        @else
                            <form method="POST" action="{{ route('chat.direct.start', $user) }}" class="mt-3">
                                @csrf
                                <button type="submit" class="btn-primary btn-sm">Mulai Chat</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-5">
            {{ $users->links() }}
        </div>
    @endif
@endsection
