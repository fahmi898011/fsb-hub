@extends('layouts.app')

{{-- Daftar penilaian kualitatif QA (PRD QA Assessment §4). --}}
{{-- AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian A --}}

@section('title', 'Penilaian Kualitatif QA')

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-semibold text-ink-900">Penilaian Kualitatif QA</h1>
        <p class="text-sm text-ink-500">Penilaian per kategori (kelebihan &amp; kekurangan) untuk tiap agent per periode.</p>
    </div>

    @include('qa.assessment._nav')

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div>
                <label for="search" class="form-label">Cari</label>
                <input id="search" name="search" type="text" value="{{ request('search') }}" class="form-input" placeholder="Nama agent / QA">
            </div>
            <div>
                <label for="period_id" class="form-label">Periode</label>
                <select id="period_id" name="period_id" class="form-input">
                    <option value="">Semua</option>
                    @foreach ($periods as $period)
                        <option value="{{ $period->id }}" @selected((int) request('period_id') === $period->id)>{{ $period->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-input">
                    <option value="">Semua</option>
                    <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                    <option value="published" @selected(request('status') === 'published')>Terbit</option>
                </select>
            </div>
            <button type="submit" class="btn-secondary">Terapkan</button>
        </form>

        @can('create', App\Models\QaAssessment::class)
            <a href="{{ route('qa.assessments.create') }}" class="btn-primary">Assessment Baru</a>
        @endcan
    </div>

    @if ($assessments->isEmpty())
        <x-empty-state title="Belum ada penilaian"
                       description="Buat assessment untuk menilai agent pada periode berjalan." />
    @else
        <div class="card overflow-hidden">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-ink-100 bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Agent</th>
                        <th class="px-5 py-3 font-semibold">QA</th>
                        <th class="px-5 py-3 font-semibold">Periode</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 text-right font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @foreach ($assessments as $assessment)
                        <tr class="hover:bg-ink-50">
                            <td class="px-5 py-3 font-medium text-ink-900">{{ $assessment->agent_name_snapshot }}</td>
                            <td class="px-5 py-3 text-ink-600">{{ $assessment->qa_name_snapshot }}</td>
                            <td class="px-5 py-3 text-ink-600">{{ $assessment->period_name_snapshot }}</td>
                            <td class="px-5 py-3">
                                <x-badge :variant="$assessment->status === 'published' ? 'green' : 'amber'">
                                    {{ $assessment->status === 'published' ? 'Terbit' : 'Draft' }}
                                </x-badge>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('qa.assessments.show', $assessment) }}" class="btn-secondary btn-sm">Lihat</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $assessments->links() }}</div>
    @endif
@endsection
