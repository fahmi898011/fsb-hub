@extends('layouts.app')

@section('title', 'Antrean Tiket IT')

@section('content')
    <div class="mb-5">
        <h1 class="text-xl font-semibold text-ink-900">Antrean Tiket IT</h1>
        <p class="text-sm text-ink-500">Semua tiket dari karyawan. Tiket mendesak selalu muncul lebih dulu.</p>
    </div>

    <form method="GET" action="{{ route('it.tickets.index') }}" class="card card-body mb-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <div class="lg:col-span-2">
                <label for="search" class="form-label">Cari</label>
                <input id="search" name="search" type="text" value="{{ $filters['search'] ?? '' }}" class="form-input"
                       placeholder="Judul atau nomor tiket">
            </div>

            <div>
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-input">
                    <option value="">Semua status</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif (belum selesai)</option>
                    <option value="open" @selected(($filters['status'] ?? '') === 'open')>Terbuka</option>
                    <option value="in_progress" @selected(($filters['status'] ?? '') === 'in_progress')>Diproses</option>
                    <option value="resolved" @selected(($filters['status'] ?? '') === 'resolved')>Selesai</option>
                    <option value="closed" @selected(($filters['status'] ?? '') === 'closed')>Ditutup</option>
                </select>
            </div>

            <div>
                <label for="priority" class="form-label">Prioritas</label>
                <select id="priority" name="priority" class="form-input">
                    <option value="">Semua prioritas</option>
                    <option value="urgent" @selected(($filters['priority'] ?? '') === 'urgent')>Mendesak</option>
                    <option value="high" @selected(($filters['priority'] ?? '') === 'high')>Tinggi</option>
                    <option value="medium" @selected(($filters['priority'] ?? '') === 'medium')>Sedang</option>
                    <option value="low" @selected(($filters['priority'] ?? '') === 'low')>Rendah</option>
                </select>
            </div>

            <div>
                <label for="category" class="form-label">Kategori</label>
                <select id="category" name="category" class="form-input">
                    <option value="">Semua kategori</option>
                    <option value="hardware" @selected(($filters['category'] ?? '') === 'hardware')>Perangkat Keras</option>
                    <option value="software" @selected(($filters['category'] ?? '') === 'software')>Perangkat Lunak</option>
                    <option value="network" @selected(($filters['category'] ?? '') === 'network')>Jaringan</option>
                    <option value="account" @selected(($filters['category'] ?? '') === 'account')>Akun &amp; Akses</option>
                    <option value="other" @selected(($filters['category'] ?? '') === 'other')>Lainnya</option>
                </select>
            </div>

            <div>
                <label for="assigned" class="form-label">Penanggung jawab</label>
                <select id="assigned" name="assigned" class="form-input">
                    <option value="">Semua tiket</option>
                    <option value="me" @selected(($filters['assigned'] ?? '') === 'me')>Tiket saya</option>
                    <option value="none" @selected(($filters['assigned'] ?? '') === 'none')>Belum ditugaskan</option>
                </select>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-2 border-t border-ink-100 pt-4">
            <button type="submit" class="btn-primary">Terapkan</button>
            <a href="{{ route('it.tickets.index') }}" class="btn-secondary">Reset</a>
        </div>
    </form>

    @if ($tickets->isEmpty())
        <x-empty-state title="Tidak ada tiket" description="Belum ada tiket yang cocok dengan filter ini." />
    @else
        {{-- Tabel (desktop) --}}
        <div class="card hidden overflow-hidden lg:block">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-ink-100 bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Nomor</th>
                        <th class="px-5 py-3 font-semibold">Tiket</th>
                        <th class="px-5 py-3 font-semibold">Pelapor</th>
                        <th class="px-5 py-3 font-semibold">Kategori</th>
                        <th class="px-5 py-3 font-semibold">Prioritas</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 font-semibold">Penanggung Jawab</th>
                        <th class="px-5 py-3 text-right font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @foreach ($tickets as $ticket)
                        <tr class="transition-colors hover:bg-ink-50/50">
                            <td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-ink-500">{{ $ticket->ticket_number }}</td>
                            <td class="max-w-xs px-5 py-3">
                                <a href="{{ route('it.tickets.show', $ticket) }}" class="font-medium text-ink-900 hover:text-brand-700">
                                    {{ $ticket->title }}
                                </a>
                                <p class="text-xs text-ink-400">{{ $ticket->created_at?->diffForHumans() }}</p>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-ink-600">{{ $ticket->user?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-ink-600">{{ $ticket->category_label }}</td>
                            <td class="whitespace-nowrap px-5 py-3"><x-ticket-priority :priority="$ticket->priority" /></td>
                            <td class="whitespace-nowrap px-5 py-3"><x-ticket-status :status="$ticket->status" /></td>
                            <td class="whitespace-nowrap px-5 py-3 text-ink-600">{{ $ticket->assignee?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-right">
                                <a href="{{ route('it.tickets.show', $ticket) }}" class="btn-secondary btn-sm">Tangani</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Kartu (mobile) --}}
        <div class="space-y-3 lg:hidden">
            @foreach ($tickets as $ticket)
                <a href="{{ route('it.tickets.show', $ticket) }}" class="card card-body block">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-ink-900">{{ $ticket->title }}</p>
                            <p class="mt-0.5 font-mono text-xs text-ink-400">{{ $ticket->ticket_number }}</p>
                        </div>
                        <x-ticket-status :status="$ticket->status" />
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-ink-100 pt-3">
                        <x-ticket-priority :priority="$ticket->priority" />
                        <span class="text-xs text-ink-500">{{ $ticket->user?->name ?? '—' }}</span>
                        <span class="text-xs text-ink-400">&middot; {{ $ticket->assignee?->name ?? 'belum ditugaskan' }}</span>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $tickets->links() }}
        </div>
    @endif
@endsection

