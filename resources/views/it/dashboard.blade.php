@extends('layouts.app')

@section('title', 'Dashboard IT')

@section('content')
    @php
        $oldestUnassigned = $stats['oldest_unassigned_days'];

        $unassignedHint = $stats['unassigned'] === 0
            ? 'Semua tiket sudah ada penanggung jawab'
            : ($oldestUnassigned === 0
                ? 'Tertua masuk hari ini'
                : 'Tertua menunggu ' . $oldestUnassigned . ' hari');

        // Warna di sini bukan hiasan: tiap warna mewakili satu status dan
        // dipakai sama persis oleh badge status tiket.
        $statusRows = [
            ['key' => 'open', 'label' => 'Terbuka', 'value' => $stats['open'], 'bar' => 'bg-brand-500'],
            ['key' => 'in_progress', 'label' => 'Diproses', 'value' => $stats['in_progress'], 'bar' => 'bg-amber-400'],
            ['key' => 'resolved', 'label' => 'Selesai', 'value' => $stats['resolved'], 'bar' => 'bg-emerald-500'],
            ['key' => 'closed', 'label' => 'Ditutup', 'value' => $stats['closed'], 'bar' => 'bg-ink-300'],
        ];
    @endphp

    {{-- Topbar sudah merender judul halaman; di sini cukup konteks + aksi. --}}
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-ink-500">Antrean dukungan teknis: apa yang perlu ditangani lebih dulu.</p>

        <a href="{{ route('it.tickets.index') }}" class="btn-primary shrink-0">Buka Antrean</a>
    </div>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
        <x-stat-card
            label="Belum ditugaskan"
            :value="$stats['unassigned']"
            :hint="$unassignedHint"
            :href="route('it.tickets.index', ['status' => 'active', 'assigned' => 'none'])"
            :tone="$stats['unassigned'] > 0 ? 'alert' : 'default'"
        />

        <x-stat-card
            label="Mendesak"
            :value="$stats['urgent']"
            :hint="$stats['urgent'] > 0 ? 'Belum selesai, tangani lebih dulu' : 'Tidak ada tiket mendesak'"
            :href="route('it.tickets.index', ['status' => 'active', 'priority' => 'urgent'])"
            :tone="$stats['urgent'] > 0 ? 'danger' : 'default'"
        />

        <x-stat-card
            label="Tiket saya"
            :value="$stats['mine']"
            hint="Sedang Anda tangani"
            :href="route('it.tickets.index', ['status' => 'active', 'assigned' => 'me'])"
        />

        <x-stat-card
            label="Selesai 7 hari"
            :value="$stats['resolved_7']"
            hint="Diselesaikan dalam 7 hari terakhir"
            tone="calm"
        />
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-panel
            class="lg:col-span-2"
            title="Antrean Aktif"
            description="Mendesak lebih dulu, lalu yang paling lama menunggu."
            :href="route('it.tickets.index', ['status' => 'active'])"
        >
            <div class="divide-y divide-ink-100">
                @forelse ($queue as $ticket)
                    <x-list-row
                        :href="route('it.tickets.show', $ticket)"
                        :title="$ticket->title"
                        :meta="$ticket->ticket_number . ' · ' . ($ticket->assignee?->name ?? 'Belum ditugaskan')"
                    >
                        <span class="hidden shrink-0 text-xs text-ink-400 sm:block">{{ $ticket->created_at?->diffForHumans() }}</span>
                        <x-ticket-priority :priority="$ticket->priority" />
                        <x-ticket-status :status="$ticket->status" />
                    </x-list-row>
                @empty
                    <p class="px-5 py-6 text-sm text-ink-400">Tidak ada tiket aktif. Antrean bersih.</p>
                @endforelse
            </div>
        </x-panel>

        <x-panel title="Distribusi Status" :description="'Dari ' . number_format($stats['total']) . ' tiket'">
            @foreach ($statusRows as $row)
                @php
                    $percent = $stats['total'] > 0
                        ? (int) round($row['value'] / $stats['total'] * 100)
                        : 0;
                @endphp

                <a href="{{ route('it.tickets.index', ['status' => $row['key']]) }}"
                   class="block px-5 py-3 transition-colors hover:bg-ink-50/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-500 sm:px-6">
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="text-sm text-ink-700">{{ $row['label'] }}</span>
                        <span class="text-sm font-semibold tabular-nums text-ink-900">{{ number_format($row['value']) }}</span>
                    </div>

                    <div class="mt-2 h-1 overflow-hidden rounded-full bg-ink-100">
                        <div class="h-full rounded-full {{ $row['bar'] }}"
                             style="width: {{ $row['value'] > 0 ? max($percent, 2) : 0 }}%"></div>
                    </div>
                </a>
            @endforeach
        </x-panel>
    </div>
@endsection

