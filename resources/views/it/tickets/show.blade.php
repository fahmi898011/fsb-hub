@extends('layouts.app')

@section('title', 'Tiket ' . $ticket->ticket_number)

@section('content')
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-semibold text-ink-900">{{ $ticket->title }}</h1>
                <x-ticket-status :status="$ticket->status" />
                <x-ticket-priority :priority="$ticket->priority" />
            </div>
            <p class="mt-1 font-mono text-sm text-ink-500">{{ $ticket->ticket_number }}</p>
        </div>

        <div class="flex shrink-0 gap-2">
            <a href="{{ route('it.tickets.index') }}" class="btn-secondary">Antrean</a>
            <a href="{{ route('it.dashboard') }}" class="btn-ghost">Dashboard</a>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
            {{-- Deskripsi --}}
            <div class="card">
                <div class="card-header flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-ink-900">Deskripsi Kendala</h2>
                    <span class="text-xs text-ink-400">{{ $ticket->category_label }}</span>
                </div>
                <div class="card-body">
                    <p class="whitespace-pre-line break-words text-sm text-ink-700">{{ $ticket->description }}</p>
                </div>
            </div>

            {{-- Percakapan + catatan internal --}}
            <div class="card overflow-hidden">
                <div class="card-header flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-ink-900">Percakapan</h2>
                    <span class="badge-gray">{{ $replies->count() }} entri</span>
                </div>

                @include('tickets._thread', ['replies' => $replies])

                <div class="border-t border-ink-100 bg-ink-50/40 px-5 py-4">
                    <form method="POST" action="{{ route('it.tickets.reply', $ticket) }}">
                        @csrf

                        <label for="message" class="form-label">Balasan</label>
                        <textarea id="message" name="message" rows="3" required maxlength="5000" class="form-input"
                                  placeholder="Tulis balasan untuk pelapor, atau catatan internal...">{{ old('message') }}</textarea>
                        @error('message')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror

                        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                            <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-600">
                                <input type="checkbox" name="is_internal" value="1" @checked(old('is_internal'))
                                       class="h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                                Jadikan catatan internal
                            </label>

                            <button type="submit" class="btn-primary btn-sm">Kirim</button>
                        </div>
                        <p class="mt-1 text-xs text-ink-400">Catatan internal tidak terlihat oleh pelapor.</p>
                    </form>
                </div>
            </div>
        </div>

        <div class="space-y-5">
            {{-- Pelapor --}}
            <div class="card">
                <div class="card-header">
                    <h2 class="text-sm font-semibold text-ink-900">Pelapor</h2>
                </div>
                <div class="card-body flex items-center gap-3">
                    <img src="{{ $ticket->user?->avatar_url }}" alt="" class="h-10 w-10 rounded-full object-cover ring-1 ring-ink-200">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-ink-900">{{ $ticket->user?->name ?? 'Pengguna dihapus' }}</p>
                        <p class="truncate text-xs text-ink-500">{{ $ticket->user?->email }}</p>
                    </div>
                </div>
                <dl class="divide-y divide-ink-100 border-t border-ink-100">
                    <div class="flex items-center justify-between gap-3 px-5 py-3">
                        <dt class="text-sm text-ink-500">Dibuat</dt>
                        <dd class="text-sm text-ink-600">{{ $ticket->created_at?->format('d M Y H:i') }}</dd>
                    </div>
                    @if ($ticket->resolved_at)
                        <div class="flex items-center justify-between gap-3 px-5 py-3">
                            <dt class="text-sm text-ink-500">Selesai</dt>
                            <dd class="text-sm text-ink-600">{{ $ticket->resolved_at->format('d M Y H:i') }}</dd>
                        </div>
                    @endif
                    @if ($ticket->closed_at)
                        <div class="flex items-center justify-between gap-3 px-5 py-3">
                            <dt class="text-sm text-ink-500">Ditutup</dt>
                            <dd class="text-sm text-ink-600">{{ $ticket->closed_at->format('d M Y H:i') }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            {{-- Penugasan --}}
            <div class="card">
                <div class="card-header">
                    <h2 class="text-sm font-semibold text-ink-900">Penanggung Jawab</h2>
                </div>

                @can('assign', $ticket)
                    <form method="POST" action="{{ route('it.tickets.assign', $ticket) }}" class="card-body">
                        @csrf

                        <label for="assigned_to" class="form-label">Tugaskan ke</label>
                        <select id="assigned_to" name="assigned_to" class="form-input">
                            <option value="">— Belum ditugaskan —</option>
                            @foreach ($agents as $agent)
                                <option value="{{ $agent->id }}" @selected((string) old('assigned_to', $ticket->assigned_to) === (string) $agent->id)>
                                    {{ $agent->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('assigned_to')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror

                        <button type="submit" class="btn-primary btn-sm mt-3">Simpan Penugasan</button>
                    </form>
                @else
                    <div class="card-body">
                        <p class="text-sm text-ink-700">{{ $ticket->assignee?->name ?? 'Belum ditugaskan' }}</p>
                    </div>
                @endcan
            </div>

            {{-- Status --}}
            <div class="card">
                <div class="card-header">
                    <h2 class="text-sm font-semibold text-ink-900">Status</h2>
                </div>

                @can('update', $ticket)
                    <form method="POST" action="{{ route('it.tickets.status', $ticket) }}" class="card-body">
                        @csrf

                        <label for="status" class="form-label">Ubah status</label>
                        <select id="status" name="status" class="form-input">
                            <option value="open" @selected(old('status', $ticket->status) === 'open')>Terbuka</option>
                            <option value="in_progress" @selected(old('status', $ticket->status) === 'in_progress')>Diproses</option>
                            <option value="resolved" @selected(old('status', $ticket->status) === 'resolved')>Selesai</option>
                            <option value="closed" @selected(old('status', $ticket->status) === 'closed')>Ditutup</option>
                        </select>
                        @error('status')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror

                        <button type="submit" class="btn-primary btn-sm mt-3">Perbarui Status</button>
                    </form>
                @else
                    <div class="card-body">
                        <x-ticket-status :status="$ticket->status" />
                    </div>
                @endcan
            </div>
        </div>
    </div>
@endsection

