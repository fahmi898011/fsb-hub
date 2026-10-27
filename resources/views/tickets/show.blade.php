@extends('layouts.app')

@section('title', 'Tiket ' . $ticket->ticket_number)

@section('content')
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-semibold text-ink-900">{{ $ticket->title }}</h1>
                <x-ticket-status :status="$ticket->status" />
            </div>
            <p class="mt-1 font-mono text-sm text-ink-500">{{ $ticket->ticket_number }}</p>
        </div>

        <a href="{{ route('tickets.index') }}" class="btn-secondary shrink-0">Kembali</a>
    </div>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
        {{-- Deskripsi --}}
        <div class="space-y-5 lg:col-span-2">
            <div class="card">
                <div class="card-header">
                    <h2 class="text-sm font-semibold text-ink-900">Deskripsi Kendala</h2>
                </div>
                <div class="card-body">
                    <p class="whitespace-pre-line break-words text-sm text-ink-700">{{ $ticket->description }}</p>
                </div>
            </div>

            {{-- Percakapan --}}
            <div class="card overflow-hidden">
                <div class="card-header flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-ink-900">Percakapan</h2>
                    <span class="badge-gray">{{ $replies->count() }} balasan</span>
                </div>

                @include('tickets._thread', ['replies' => $replies])

                <div class="border-t border-ink-100 bg-ink-50/40 px-5 py-4">
                    @if ($ticket->status === 'closed')
                        <p class="text-sm text-ink-500">
                            Tiket ini sudah ditutup. Buat tiket baru bila kendala muncul kembali.
                        </p>
                    @else
                        <form method="POST" action="{{ route('tickets.reply', $ticket) }}">
                            @csrf

                            <label for="message" class="form-label">Tambahkan keterangan</label>
                            <textarea id="message" name="message" rows="3" required maxlength="5000" class="form-input"
                                      placeholder="Berikan informasi tambahan untuk tim IT...">{{ old('message') }}</textarea>
                            @error('message')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror

                            <div class="mt-3">
                                <button type="submit" class="btn-primary btn-sm">Kirim Balasan</button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        {{-- Ringkasan --}}
        <div class="space-y-5">
            <div class="card">
                <div class="card-header">
                    <h2 class="text-sm font-semibold text-ink-900">Ringkasan</h2>
                </div>
                <dl class="divide-y divide-ink-100">
                    <div class="flex items-center justify-between gap-3 px-5 py-3">
                        <dt class="text-sm text-ink-500">Status</dt>
                        <dd><x-ticket-status :status="$ticket->status" /></dd>
                    </div>
                    <div class="flex items-center justify-between gap-3 px-5 py-3">
                        <dt class="text-sm text-ink-500">Prioritas</dt>
                        <dd><x-ticket-priority :priority="$ticket->priority" /></dd>
                    </div>
                    <div class="flex items-center justify-between gap-3 px-5 py-3">
                        <dt class="text-sm text-ink-500">Kategori</dt>
                        <dd class="text-sm font-medium text-ink-800">{{ $ticket->category_label }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3 px-5 py-3">
                        <dt class="text-sm text-ink-500">Ditangani</dt>
                        <dd class="text-sm font-medium text-ink-800">{{ $ticket->assignee?->name ?? 'Belum ditugaskan' }}</dd>
                    </div>
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
                </dl>
            </div>
        </div>
    </div>
@endsection
