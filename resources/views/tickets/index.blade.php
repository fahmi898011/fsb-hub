@extends('layouts.app')

@section('title', 'Tiket Saya')

@section('content')
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-ink-900">Tiket Saya</h1>
            <p class="text-sm text-ink-500">Laporan kendala yang Anda kirim ke tim IT beserta perkembangannya.</p>
        </div>

        <a href="{{ route('tickets.create') }}" class="btn-primary shrink-0">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
            </svg>
            Buat Tiket
        </a>
    </div>

    <form method="GET" action="{{ route('tickets.index') }}" class="card card-body mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="sm:w-56">
            <label for="status" class="form-label">Status</label>
            <select id="status" name="status" class="form-input">
                <option value="">Semua status</option>
                <option value="open" @selected(($filters['status'] ?? '') === 'open')>Terbuka</option>
                <option value="in_progress" @selected(($filters['status'] ?? '') === 'in_progress')>Diproses</option>
                <option value="resolved" @selected(($filters['status'] ?? '') === 'resolved')>Selesai</option>
                <option value="closed" @selected(($filters['status'] ?? '') === 'closed')>Ditutup</option>
            </select>
        </div>

        <button type="submit" class="btn-secondary sm:shrink-0">Terapkan</button>

        @if (($filters['status'] ?? null))
            <a href="{{ route('tickets.index') }}" class="btn-ghost sm:shrink-0">Reset</a>
        @endif
    </form>

    @if ($tickets->isEmpty())
        <x-empty-state
            title="Belum ada tiket"
            description="Ada kendala perangkat, jaringan, akun, atau aplikasi? Kirim tiket agar tim IT dapat menindaklanjuti."
        >
            <a href="{{ route('tickets.create') }}" class="btn-primary">Buat Tiket</a>
        </x-empty-state>
    @else
        {{-- Tabel (desktop) --}}
        <div class="card hidden overflow-hidden lg:block">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-ink-100 bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Nomor</th>
                        <th class="px-5 py-3 font-semibold">Judul</th>
                        <th class="px-5 py-3 font-semibold">Kategori</th>
                        <th class="px-5 py-3 font-semibold">Prioritas</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 font-semibold">Diperbarui</th>
                        <th class="px-5 py-3 text-right font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @foreach ($tickets as $ticket)
                        <tr class="transition-colors hover:bg-ink-50/50">
                            <td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-ink-500">{{ $ticket->ticket_number }}</td>
                            <td class="max-w-xs px-5 py-3">
                                <a href="{{ route('tickets.show', $ticket) }}" class="font-medium text-ink-900 hover:text-brand-700">
                                    {{ $ticket->title }}
                                </a>
                                <p class="text-xs text-ink-400">
                                    {{ $ticket->assignee ? 'Ditangani ' . $ticket->assignee->name : 'Belum ditugaskan' }}
                                </p>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-ink-600">{{ $ticket->category_label }}</td>
                            <td class="whitespace-nowrap px-5 py-3"><x-ticket-priority :priority="$ticket->priority" /></td>
                            <td class="whitespace-nowrap px-5 py-3"><x-ticket-status :status="$ticket->status" /></td>
                            <td class="whitespace-nowrap px-5 py-3 text-ink-500">{{ $ticket->updated_at?->diffForHumans() }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-right">
                                <a href="{{ route('tickets.show', $ticket) }}" class="btn-secondary btn-sm">Detail</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Kartu (mobile) --}}
        <div class="space-y-3 lg:hidden">
            @foreach ($tickets as $ticket)
                <a href="{{ route('tickets.show', $ticket) }}" class="card card-body block">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-ink-900">{{ $ticket->title }}</p>
                            <p class="mt-0.5 font-mono text-xs text-ink-400">{{ $ticket->ticket_number }}</p>
                        </div>
                        <x-ticket-status :status="$ticket->status" />
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-ink-100 pt-3">
                        <x-ticket-priority :priority="$ticket->priority" />
                        <span class="text-xs text-ink-500">{{ $ticket->category_label }}</span>
                        <span class="text-xs text-ink-400">&middot; {{ $ticket->updated_at?->diffForHumans() }}</span>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $tickets->links() }}
        </div>
    @endif
@endsection
