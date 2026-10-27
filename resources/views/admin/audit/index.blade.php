@extends('layouts.app')

@section('title', 'Audit Log')

{{--
    Halaman Audit Log (Admin).
    AGENT: asisten | 2026-10-10 | kolom jejak eksekusi + pencarian/modul/hasil + ekspor CSV | modul Audit Log, §11.15
--}}

@section('content')
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-ink-900">Audit Log</h1>
            <p class="text-sm text-ink-500">Jejak eksekusi: siapa melakukan apa, lewat permintaan apa, dan hasilnya.</p>
        </div>

        <div class="flex shrink-0 flex-wrap items-center gap-2">
            <a href="{{ route('admin.audit.pdf', array_filter($filters, fn ($value) => $value !== '')) }}"
               class="btn-secondary">Ekspor PDF</a>
            <a href="{{ route('admin.audit.export', array_filter($filters, fn ($value) => $value !== '')) }}"
               class="btn-secondary">Ekspor CSV</a>
        </div>
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ route('admin.audit.index') }}" class="card card-body mb-5">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="sm:col-span-2">
                <label for="q" class="form-label">Cari</label>
                <input id="q" name="q" type="search" value="{{ $filters['q'] }}" class="form-input"
                       placeholder="Deskripsi, pelaku, label entitas, atau nama aksi">
            </div>

            <div>
                <label for="action" class="form-label">Aksi</label>
                <select id="action" name="action" class="form-input">
                    <option value="">Semua aksi</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}" @selected($filters['action'] === $action)>{{ $action }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="module" class="form-label">Modul</label>
                <select id="module" name="module" class="form-input">
                    <option value="">Semua modul</option>
                    @foreach ($modules as $module)
                        <option value="{{ $module }}" @selected($filters['module'] === $module)>
                            {{ \App\Models\AuditLog::moduleLabelFor($module) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="result" class="form-label">Hasil</label>
                <select id="result" name="result" class="form-input">
                    <option value="">Semua hasil</option>
                    @foreach ($results as $result)
                        <option value="{{ $result }}" @selected($filters['result'] === $result)>
                            {{ \App\Models\AuditLog::resultLabelFor($result) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="user" class="form-label">Pelaku</label>
                <select id="user" name="user" class="form-input">
                    <option value="">Semua pengguna</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((string) $filters['user'] === (string) $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="subject" class="form-label">Entitas</label>
                <select id="subject" name="subject" class="form-input">
                    <option value="">Semua entitas</option>
                    @foreach ($subjects as $subject)
                        <option value="{{ $subject }}" @selected($filters['subject'] === $subject)>
                            {{ \App\Models\AuditLog::subjectLabel($subject) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="from" class="form-label">Dari tanggal</label>
                <input id="from" name="from" type="date" value="{{ $filters['from'] }}" class="form-input">
            </div>

            <div>
                <label for="to" class="form-label">Sampai tanggal</label>
                <input id="to" name="to" type="date" value="{{ $filters['to'] }}" class="form-input">
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-2 border-t border-ink-100 pt-4">
            <button type="submit" class="btn-primary">Terapkan</button>
            <a href="{{ route('admin.audit.index') }}" class="btn-secondary">Reset</a>
        </div>
    </form>

    @if ($logs->isEmpty())
        <x-empty-state title="Belum ada catatan audit" description="Aktivitas perubahan data akan muncul di sini." />
    @else
        {{-- Tabel (desktop) --}}
        <div class="card hidden overflow-hidden lg:block">
            <table class="w-full text-sm">
                <thead class="border-b border-ink-100 bg-ink-50/60 text-left text-xs uppercase tracking-wide text-ink-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Waktu</th>
                        <th class="px-4 py-3 font-semibold">Pelaku</th>
                        <th class="px-4 py-3 font-semibold">Modul</th>
                        <th class="px-4 py-3 font-semibold">Aktivitas</th>
                        <th class="px-4 py-3 font-semibold">Entitas</th>
                        <th class="px-4 py-3 font-semibold">Hasil</th>
                        <th class="px-4 py-3 text-right font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @foreach ($logs as $log)
                        <tr class="transition-colors hover:bg-ink-50/50">
                            <td class="whitespace-nowrap px-4 py-3 align-top text-ink-600">{{ $log->created_at?->format('d M Y H:i') }}</td>
                            <td class="px-4 py-3 align-top">
                                <p class="font-medium text-ink-800">{{ $log->actorLabel() }}</p>
                                <p class="text-xs text-ink-500">
                                    {{ $log->actor_role ? ucfirst($log->actor_role).' · ' : '' }}{{ $log->actorEmail() ?? '—' }}
                                </p>
                            </td>
                            <td class="px-4 py-3 align-top text-ink-600">{{ $log->moduleLabel() }}</td>
                            <td class="px-4 py-3 align-top">
                                <p class="text-ink-800">{{ $log->descriptionText() }}</p>
                                <p class="mt-1 flex flex-wrap items-center gap-1">
                                    <x-badge variant="brand">{{ $log->action }}</x-badge>
                                    @if ($log->severity)
                                        <x-badge :variant="$log->severityVariant()">{{ $log->severityLabel() }}</x-badge>
                                    @endif
                                </p>
                            </td>
                            <td class="px-4 py-3 align-top text-ink-600">
                                @if ($log->subject_type)
                                    {{ $log->subjectTypeLabel() }}@if ($log->subject_id)<span class="text-ink-400"> #{{ $log->subject_id }}</span>@endif
                                    @if ($log->subject_label)
                                        <span class="block text-xs text-ink-500">{{ $log->subject_label }}</span>
                                    @endif
                                @else
                                    <span class="text-ink-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 align-top">
                                <x-badge :variant="$log->resultVariant()">{{ $log->resultLabel() }}</x-badge>
                                @if ($log->status_code)
                                    <span class="mt-1 block text-xs text-ink-500">HTTP {{ $log->status_code }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right align-top">
                                <a href="{{ route('admin.audit.show', $log) }}" class="btn-secondary btn-sm">Detail</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Kartu (mobile) --}}
        <div class="space-y-3 lg:hidden">
            @foreach ($logs as $log)
                <div class="card card-body">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-ink-900">{{ $log->actorLabel() }}</p>
                            <p class="text-xs text-ink-500">{{ $log->created_at?->format('d M Y H:i') }}</p>
                        </div>
                        <x-badge :variant="$log->resultVariant()">{{ $log->resultLabel() }}</x-badge>
                    </div>

                    <p class="mt-3 text-sm text-ink-800">{{ $log->descriptionText() }}</p>
                    <p class="mt-1"><x-badge variant="brand">{{ $log->action }}</x-badge></p>

                    <div class="mt-3 grid grid-cols-2 gap-2 border-t border-ink-100 pt-3 text-xs text-ink-500">
                        <p>
                            <span class="font-medium text-ink-700">Modul:</span>
                            {{ $log->moduleLabel() }}
                        </p>
                        <p>
                            <span class="font-medium text-ink-700">Entitas:</span>
                            {{ $log->subjectLabelText() }}
                        </p>
                        <p><span class="font-medium text-ink-700">IP:</span> {{ $log->ip_address ?? '—' }}</p>
                        <p><span class="font-medium text-ink-700">Tingkat:</span> {{ $log->severityLabel() }}</p>
                    </div>

                    <div class="mt-3">
                        <a href="{{ route('admin.audit.show', $log) }}" class="btn-secondary btn-sm">Detail</a>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-5">
            {{ $logs->links() }}
        </div>
    @endif
@endsection
