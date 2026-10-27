@extends('layouts.app')

{{-- Halaman muka Admin: keadaan wadah aplikasi, bukan isi konten. --}}
{{-- AGENT: asisten | 2026-10-16 | buat berkas baru | modul Dashboard per peran --}}

@section('title', 'Dashboard Admin')

@section('content')
    @php
        $roleHint = $stats['users_without_role'] === 0
            ? 'Semua akun sudah punya peran'
            : 'Tidak bisa membuka menu apa pun';

        // Tiga baris "kesiapan akun": akun yang ada belum tentu bisa dipakai.
        // Angkanya selalu berpasangan dengan penyebut, jadi bar di bawahnya
        // punya arti — bukan hiasan.
        // AGENT: asisten | 2026-10-20 | ganti blok Pintasan dengan kesiapan akun | modul Dashboard Admin
        $accessRows = [
            [
                'label' => 'Akun aktif',
                'value' => $stats['users_active'],
                'hint' => 'Bisa masuk ke aplikasi',
                'tone' => 'default',
            ],
            [
                'label' => 'Punya peran',
                'value' => $stats['users_with_role'],
                'hint' => 'Peran menentukan menu yang terbuka',
                'tone' => $stats['users_with_role'] < $stats['users'] ? 'danger' : 'default',
            ],
            [
                'label' => 'Punya divisi',
                'value' => $stats['users_with_division'],
                'hint' => 'Dasar laporan per divisi',
                'tone' => 'default',
            ],
        ];
    @endphp

    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-ink-500">Kondisi wadah aplikasi: akun, struktur, dan jejak perubahan terakhir.</p>

        <a href="{{ route('admin.users.index') }}" class="btn-primary shrink-0">Kelola Pengguna</a>
    </div>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
        <x-stat-card
            label="Pengguna"
            :value="number_format($stats['users'])"
            :hint="'Tersebar di ' . $roleCount . ' peran'"
            :href="route('admin.users.index')"
        />

        <x-stat-card
            label="Tanpa peran"
            :value="number_format($stats['users_without_role'])"
            :hint="$roleHint"
            :href="route('admin.users.index', ['role' => 'none'])"
            :tone="$stats['users_without_role'] > 0 ? 'danger' : 'calm'"
        />

        <x-stat-card
            label="Akun nonaktif"
            :value="number_format($stats['inactive_users'])"
            hint="Tidak bisa masuk ke aplikasi"
            :href="route('admin.users.index', ['status' => 'inactive'])"
        />

        <x-stat-card
            label="Perubahan 7 hari"
            :value="number_format($stats['changes'])"
            hint="Baris pada audit log"
            :href="route('admin.audit.index')"
        />
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-panel
            class="lg:col-span-2"
            title="Perubahan Terakhir"
            description="Apa yang berubah pada data aplikasi, terbaru lebih dulu."
            :href="route('admin.audit.index')"
            link-label="Buka audit log"
        >
            <div class="divide-y divide-ink-100">
                @forelse ($recentChanges as $log)
                    <x-list-row
                        :href="route('admin.audit.show', $log)"
                        :title="$log->descriptionText()"
                        :meta="$log->actorLabel() . ' · ' . $log->created_at?->diffForHumans()"
                    >
                        <x-badge :variant="$log->resultVariant()">{{ $log->resultLabel() }}</x-badge>
                    </x-list-row>
                @empty
                    <p class="px-5 py-6 text-sm text-ink-400">Belum ada perubahan data yang tercatat.</p>
                @endforelse
            </div>
        </x-panel>

        <x-panel
            title="Kesiapan Akun"
            description="Akun yang ada belum tentu bisa dipakai."
            :href="route('admin.users.index')"
            link-label="Buka pengguna"
        >
            <div class="divide-y divide-ink-100">
                @foreach ($accessRows as $row)
                    <div class="px-5 py-3 sm:px-6">
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="text-sm font-medium text-ink-900">{{ $row['label'] }}</p>
                            <p class="text-sm tabular-nums text-ink-600">
                                {{ number_format($row['value']) }}
                                <span class="text-xs text-ink-400">dari {{ number_format($stats['users']) }}</span>
                            </p>
                        </div>
                        <x-score-bar class="mt-2" :value="$row['value']" :max="$stats['users']" :tone="$row['tone']" />
                        <p class="mt-1 text-xs text-ink-500">{{ $row['hint'] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="flex flex-wrap gap-x-4 gap-y-1 border-t border-ink-100 px-5 py-3 text-xs text-ink-500 sm:px-6">
                <a href="{{ route('admin.departments.index') }}" class="hover:text-brand-700">{{ $stats['departments'] }} divisi</a>
                <a href="{{ route('admin.teams.index') }}" class="hover:text-brand-700">{{ $stats['teams'] }} tim</a>
                <span class="{{ $stats['teams_without_leader'] > 0 ? 'font-medium text-amber-700' : '' }}">
                    {{ $stats['teams_without_leader'] }} tim tanpa pemimpin
                </span>
            </div>
        </x-panel>
    </div>

    @php
        $showRoles = $stats['users_without_role'] > 0;
        $showTickets = auth()->user()->can('manage-tickets') && $stats['unassigned_tickets'] > 0;
    @endphp

    @if ($showRoles || $showTickets)
        <div class="mt-6 grid grid-cols-1 gap-6 {{ $showRoles && $showTickets ? 'lg:grid-cols-2' : '' }}">
            @if ($showRoles)
                <x-panel
                    title="Akun Tanpa Peran"
                    description="Akun ini bisa masuk, tetapi tidak melihat menu apa pun."
                    :href="route('admin.users.index')"
                    link-label="Perbaiki"
                >
                    <div class="divide-y divide-ink-100">
                        @foreach ($usersWithoutRole as $user)
                            <x-list-row
                                :href="route('admin.users.show', $user)"
                                :title="$user->name"
                                :meta="$user->email"
                            />
                        @endforeach
                    </div>
                </x-panel>
            @endif

            @if ($showTickets)
                <x-panel
                    title="Tiket Belum Ditugaskan"
                    description="Tiket terbuka tanpa penanggung jawab — paling lama menunggu lebih dulu."
                    :href="route('it.tickets.index', ['status' => 'active', 'assigned' => 'none'])"
                    link-label="Buka antrean"
                >
                    <div class="divide-y divide-ink-100">
                        @foreach ($unassignedTickets as $ticket)
                            <x-list-row
                                :href="route('it.tickets.show', $ticket)"
                                :title="$ticket->title"
                                :meta="$ticket->ticket_number . ' · ' . $ticket->created_at?->diffForHumans()"
                            >
                                <x-ticket-priority :priority="$ticket->priority" />
                            </x-list-row>
                        @endforeach
                    </div>
                </x-panel>
            @endif
        </div>
    @endif
@endsection
