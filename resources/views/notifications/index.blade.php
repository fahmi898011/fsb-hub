@extends('layouts.app')

@section('title', 'Notifikasi')

@section('content')
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-ink-900">Notifikasi</h1>
            <p class="text-sm text-ink-500">Pemberitahuan aktivitas yang berkaitan dengan Anda.</p>
        </div>

        @if ($unreadCount > 0)
            <form method="POST" action="{{ route('notifications.readAll') }}">
                @csrf
                <button type="submit" class="btn-secondary btn-sm">
                    Tandai semua sudah dibaca
                </button>
            </form>
        @endif
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if ($notifications->isEmpty())
        <x-empty-state
            title="Belum ada notifikasi"
            description="Notifikasi akan muncul di sini ketika ada informasi baru atau balasan tiket untuk Anda."
        />
    @else
        <div class="card divide-y divide-ink-100">
            @foreach ($notifications as $notification)
                @php($isUnread = $notification->read_at === null)

                <div class="flex items-start gap-3 px-4 py-4 sm:px-5 {{ $isUnread ? 'bg-brand-50/40' : '' }}">
                    <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $isUnread ? 'bg-brand-100 text-brand-700' : 'bg-ink-100 text-ink-500' }}">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h11Zm0 0a3 3 0 0 1-6 0" />
                        </svg>
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-semibold text-ink-900">{{ $notification->title }}</p>
                            @if ($isUnread)
                                <x-badge variant="brand">Baru</x-badge>
                            @endif
                        </div>

                        @if ($notification->message)
                            <p class="mt-0.5 text-sm text-ink-600">{{ $notification->message }}</p>
                        @endif

                        <p class="mt-1 text-xs text-ink-400">{{ $notification->created_at->diffForHumans() }}</p>
                    </div>

                    @if ($isUnread)
                        <form method="POST" action="{{ route('notifications.read', $notification) }}" class="shrink-0">
                            @csrf
                            <button type="submit" class="btn-ghost btn-sm">Buka</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="mt-5">
            {{ $notifications->links() }}
        </div>
    @endif
@endsection
