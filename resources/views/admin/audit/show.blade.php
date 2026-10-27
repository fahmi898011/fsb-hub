@extends('layouts.app')

@section('title', 'Detail Audit Log')

{{--
    Detail Audit Log (Admin).
    AGENT: asisten | 2026-10-10 | konteks eksekusi + diff berpenanda | modul Audit Log, §11.15
--}}

@section('content')
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-ink-900">Detail Audit Log</h1>
            <p class="text-sm text-ink-500">{{ $log->descriptionText() }}</p>
            <p class="text-xs text-ink-400">{{ $log->created_at?->format('d M Y H:i:s') }} · {{ $log->action }}</p>
        </div>

        <a href="{{ route('admin.audit.index') }}" class="btn-secondary shrink-0">Kembali</a>
    </div>

    {{-- Ringkasan --}}
    <div class="card card-body">
        <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-ink-500">Waktu</dt>
                <dd class="mt-0.5 text-sm text-ink-800">{{ $log->created_at?->format('d M Y H:i:s') }}</dd>
            </div>

            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-ink-500">Pelaku</dt>
                <dd class="mt-0.5 text-sm text-ink-800">
                    {{ $log->actorLabel() }}
                    @if ($log->actorEmail())<span class="text-ink-400">({{ $log->actorEmail() }})</span>@endif
                    @if ($log->actor_role)<span class="text-ink-400"> · {{ ucfirst($log->actor_role) }}</span>@endif
                    @unless ($log->user)
                        {{-- Akun pelakunya tidak (lagi) ada: potret di atas yang tersisa. --}}
                        <span class="text-ink-400"> · akun tidak ditemukan</span>
                    @endunless
                </dd>
            </div>

            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-ink-500">Aksi</dt>
                <dd class="mt-0.5"><x-badge variant="brand">{{ $log->action }}</x-badge></dd>
            </div>

            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-ink-500">Hasil</dt>
                <dd class="mt-0.5 flex flex-wrap items-center gap-1">
                    <x-badge :variant="$log->resultVariant()">{{ $log->resultLabel() }}</x-badge>
                    @if ($log->severity)
                        <x-badge :variant="$log->severityVariant()">{{ $log->severityLabel() }}</x-badge>
                    @endif
                </dd>
            </div>

            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-ink-500">Modul</dt>
                <dd class="mt-0.5 text-sm text-ink-800">{{ $log->moduleLabel() }}</dd>
            </div>

            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-ink-500">Kategori</dt>
                <dd class="mt-0.5 text-sm text-ink-800">{{ $log->category ?? '—' }}</dd>
            </div>

            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-ink-500">Entitas</dt>
                <dd class="mt-0.5 text-sm text-ink-800">
                    @if ($log->subject_type)
                        {{ $log->subjectTypeLabel() }}@if ($log->subject_id)<span class="text-ink-400"> #{{ $log->subject_id }}</span>@endif
                        @if ($log->subject_label)
                            <span class="block text-xs text-ink-500">{{ $log->subject_label }}</span>
                        @endif
                    @else
                        <span class="text-ink-400">—</span>
                    @endif
                </dd>
            </div>

            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-ink-500">Kolom berubah</dt>
                <dd class="mt-0.5 text-sm text-ink-800">
                    {{ $log->changedFieldList() ? implode(', ', $log->changedFieldList()) : '—' }}
                </dd>
            </div>
        </dl>
    </div>


    {{-- Konteks eksekusi --}}
    <div class="card card-body mt-5">
        <h2 class="text-sm font-semibold text-ink-900">Konteks Eksekusi</h2>
        <p class="mt-0.5 text-xs text-ink-500">Permintaan HTTP yang memicu kejadian ini.</p>

        <dl class="mt-4 grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-ink-500">Metode</dt>
                <dd class="mt-0.5 text-sm text-ink-800">{{ $log->method ?? '—' }}</dd>
            </div>

            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-ink-500">Status HTTP</dt>
                <dd class="mt-0.5 text-sm text-ink-800">{{ $log->status_code ?? '—' }}</dd>
            </div>

            <div class="sm:col-span-2">
                <dt class="text-xs font-medium uppercase tracking-wide text-ink-500">Rute</dt>
                <dd class="mt-0.5 text-sm text-ink-800">{{ $log->route_name ?? '—' }}</dd>
            </div>

            <div class="sm:col-span-2">
                <dt class="text-xs font-medium uppercase tracking-wide text-ink-500">URL</dt>
                <dd class="mt-0.5 break-all text-sm text-ink-800">{{ $log->url ?? '—' }}</dd>
            </div>

            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-ink-500">IP Address</dt>
                <dd class="mt-0.5 text-sm text-ink-800">{{ $log->ip_address ?? '—' }}</dd>
            </div>

            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-ink-500">Id Permintaan</dt>
                <dd class="mt-0.5 break-all font-mono text-xs text-ink-800">{{ $log->request_id ?? '—' }}</dd>
            </div>

            <div class="sm:col-span-2">
                <dt class="text-xs font-medium uppercase tracking-wide text-ink-500">User Agent</dt>
                <dd class="mt-0.5 break-words text-sm text-ink-500">{{ $log->user_agent ?? '—' }}</dd>
            </div>
        </dl>
    </div>

    {{-- Perubahan --}}
    <div class="card mt-5 overflow-hidden">
        <div class="card-header">
            <h2 class="text-sm font-semibold text-ink-900">Perubahan Data</h2>
            <p class="mt-0.5 text-xs text-ink-500">Baris bertanda "berubah" adalah kolom yang nilainya berbeda.</p>
        </div>

        @php
            $rows = $log->diffRows();
        @endphp

        @if (empty($rows))
            <div class="card-body">
                <p class="text-sm text-ink-500">Tidak ada nilai sebelum/sesudah yang tercatat untuk kejadian ini.</p>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="border-b border-ink-100 bg-ink-50/60 text-left text-xs uppercase tracking-wide text-ink-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Kolom</th>
                        <th class="px-5 py-3 font-semibold">Sebelum</th>
                        <th class="px-5 py-3 font-semibold">Sesudah</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @foreach ($rows as $row)
                        <tr class="{{ $row['changed'] ? 'bg-amber-50/60' : '' }}">
                            <th class="w-1/5 px-5 py-2.5 text-left align-top font-medium text-ink-600">
                                {{ $row['field'] }}
                                @if ($row['changed'])
                                    <span class="block text-xs font-normal text-amber-700">berubah</span>
                                @endif
                            </th>
                            <td class="break-words px-5 py-2.5 align-top text-ink-800">{{ \App\Models\AuditLog::displayValue($row['old']) }}</td>
                            <td class="break-words px-5 py-2.5 align-top text-ink-800">{{ \App\Models\AuditLog::displayValue($row['new']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
