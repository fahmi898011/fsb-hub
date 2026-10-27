@extends('layouts.app')

{{-- Penugasan QA ke agent (PRD QA Assessment §6). --}}
{{-- AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian C --}}

@section('title', 'Penugasan QA')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-ink-900">Penugasan QA ke Agent</h1>
            <p class="text-sm text-ink-500">Menentukan QA mana yang menilai agent mana (ditetapkan admin).</p>
        </div>
        @can('create', App\Models\QaAgentAssignment::class)
            <a href="{{ route('qa.agent-assignments.create') }}" class="btn-primary">Tambah Penugasan</a>
        @endcan
    </div>

    @include('qa.assessment._nav')

    <div class="mb-4">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div>
                <label for="qa_id" class="form-label">QA</label>
                <select id="qa_id" name="qa_id" class="form-input">
                    <option value="">Semua</option>
                    @foreach ($qas as $qa)
                        <option value="{{ $qa->id }}" @selected((int) request('qa_id') === $qa->id)>{{ $qa->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="agent_id" class="form-label">Agent</label>
                <select id="agent_id" name="agent_id" class="form-input">
                    <option value="">Semua</option>
                    @foreach ($agents as $agent)
                        <option value="{{ $agent->id }}" @selected((int) request('agent_id') === $agent->id)>{{ $agent->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="active" class="form-label">Status</label>
                <select id="active" name="active" class="form-input">
                    <option value="">Semua</option>
                    <option value="1" @selected(request('active') === '1')>Aktif</option>
                    <option value="0" @selected(request('active') === '0')>Nonaktif</option>
                </select>
            </div>
            <button type="submit" class="btn-secondary">Terapkan</button>
        </form>
    </div>

    @if ($assignments->isEmpty())
        <x-empty-state title="Belum ada penugasan"
                       description="Tetapkan QA sebagai penilai untuk tiap agent." />
    @else
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-ink-100 bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold">QA</th>
                            <th class="px-5 py-3 font-semibold">Agent</th>
                            <th class="px-5 py-3 font-semibold">Ditetapkan oleh</th>
                            <th class="px-5 py-3 font-semibold">Catatan</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 text-right font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach ($assignments as $assignment)
                            <tr class="hover:bg-ink-50">
                                <td class="px-5 py-3 font-medium text-ink-900">{{ $assignment->qa?->name }}</td>
                                <td class="px-5 py-3 text-ink-600">{{ $assignment->agent?->name }}</td>
                                <td class="px-5 py-3 text-ink-600">{{ $assignment->assignedBy?->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-ink-600">{{ \Illuminate\Support\Str::limit($assignment->notes, 50) ?: '—' }}</td>
                                <td class="px-5 py-3">
                                    <x-badge :variant="$assignment->active ? 'green' : 'gray'">
                                        {{ $assignment->active ? 'Aktif' : 'Nonaktif' }}
                                    </x-badge>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('qa.agent-assignments.show', $assignment) }}" class="text-brand-700 hover:underline">Lihat</a>
                                    @can('update', $assignment)
                                        <a href="{{ route('qa.agent-assignments.edit', $assignment) }}" class="ml-3 text-brand-700 hover:underline">Ubah</a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3">{{ $assignments->links() }}</div>
        </div>
    @endif
@endsection
