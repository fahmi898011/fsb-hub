@extends('layouts.app')

{{-- Daftar ujian yang perlu/menunggu penilaian QA (PRD QA Assessment §5). --}}
{{-- AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian B --}}

@section('title', 'Penilaian Ujian')

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-semibold text-ink-900">Penilaian Ujian</h1>
        <p class="text-sm text-ink-500">Tinjau jawaban essay agent, beri skor, lalu publikasikan hasilnya.</p>
    </div>

    @include('qa.assessment._nav')

    {{-- Antrean yang menyusut harus terbaca sebagai "dibatasi", bukan sebagai
         "datanya hilang". --}}
    @if (! empty($scopeNotice ?? null))
        <div class="mb-4 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-900">
            <span class="font-semibold">Cakupan data:</span> {{ $scopeNotice }}
        </div>
    @endif

    <div class="mb-4">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div>
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-input">
                    <option value="">Semua</option>
                    <option value="submitted" @selected(request('status') === 'submitted')>Menunggu dinilai</option>
                    <option value="graded" @selected(request('status') === 'graded')>Sudah dinilai</option>
                    <option value="published" @selected(request('status') === 'published')>Terbit</option>
                </select>
            </div>
            <button type="submit" class="btn-secondary">Terapkan</button>
        </form>
    </div>

    @if ($assignments->isEmpty())
        <x-empty-state title="Tidak ada ujian untuk dinilai"
                       description="Ujian yang sudah dikumpulkan agent akan muncul di sini." />
    @else
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-ink-100 bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Agent</th>
                            <th class="px-5 py-3 font-semibold">Form</th>
                            <th class="px-5 py-3 font-semibold">Attempt</th>
                            <th class="px-5 py-3 font-semibold">Dikumpulkan</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 font-semibold">Nilai</th>
                            <th class="px-5 py-3 text-right font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach ($assignments as $assignment)
                            <tr class="hover:bg-ink-50">
                                <td class="px-5 py-3">
                                    <p class="font-medium text-ink-900">{{ $assignment->agent?->name }}</p>
                                    <p class="text-xs text-ink-500">{{ $assignment->agent?->employee_id }}</p>
                                </td>
                                <td class="px-5 py-3 text-ink-600">{{ $assignment->form?->title }}</td>
                                <td class="px-5 py-3 text-ink-600">ke-{{ $assignment->attempt_ke }}</td>
                                <td class="px-5 py-3 text-ink-600">{{ $assignment->completed_at?->format('d M Y H:i') ?? '—' }}</td>
                                <td class="px-5 py-3">
                                    <x-badge :variant="match($assignment->status) {
                                        'published' => 'green',
                                        'graded' => 'brand',
                                        default => 'amber',
                                    }">{{ ucfirst(str_replace('_', ' ', $assignment->status)) }}</x-badge>
                                </td>
                                <td class="px-5 py-3 text-ink-600">
                                    @if ($assignment->percentage !== null)
                                        {{ rtrim(rtrim(number_format((float) $assignment->percentage, 2, '.', ''), '0'), '.') }}%
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    @can('grade', $assignment)
                                        <a href="{{ route('qa.exam.grading.show', $assignment) }}" class="text-brand-700 hover:underline">Nilai</a>
                                    @else
                                        {{-- Tanpa izin menilai, halaman detail menolak
                                             (`gradingShow` memakai policy `grade`), jadi
                                             tautan "Lihat" di sini hanya akan berakhir 403. --}}
                                        <span class="text-ink-300">—</span>
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
