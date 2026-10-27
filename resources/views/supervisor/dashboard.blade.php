@extends('layouts.app')

{{-- Halaman muka Supervisor: siapa di tim ini yang perlu dibina, dan apa yang
     menunggu diperiksa.

     Kedua antrean (assessment & ujian) berbagi SATU panel karena pertanyaan
     penggunanya sama: "apa yang belum selesai?". Sebelumnya masing-masing
     punya panel sendiri, sehingga halaman memanjang dan separuh lebar layar
     kosong tanpa menambah informasi. --}}
{{-- AGENT: asisten | 2026-10-20 | buat berkas baru | modul Dashboard per peran --}}
{{-- AGENT: asisten | 2026-10-20 | rombak tata letak: meter skor + antrean gabungan, hapus pintasan | UI/UX --}}

@section('title', 'Dashboard Tim')

@section('content')
    @php
        $teamLine = $teams
            ->map(fn ($team) => $team->name . ($team->department ? ' · ' . $team->department->name : ''))
            ->join(', ');
    @endphp

    {{-- Topbar sudah merender judul halaman; di sini cukup konteks + aksi. --}}
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <p class="text-sm text-ink-500">
                Skor {{ $windowDays }} hari terakhir untuk tim yang Anda pimpin, diurutkan dari yang paling perlu dibina.
            </p>

            @if ($teamLine !== '')
                <p class="mt-1 truncate text-xs text-ink-500">
                    <span class="font-medium text-ink-600">Tim:</span> {{ $teamLine }}
                </p>
            @endif
        </div>

        <a href="{{ route('qa.sampling.report.dashboard') }}" class="btn-primary shrink-0">Buka Laporan QA</a>
    </div>

    {{-- Angka yang lebih kecil dari yang diharapkan harus dijelaskan, bukan
         dibiarkan terlihat seperti data yang hilang. --}}
    @if ($scopeNotice)
        <div class="mb-4 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-900">
            <span class="font-semibold">Cakupan data:</span> {{ $scopeNotice }}
        </div>
    @endif

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
        <x-stat-card
            label="Anggota tim"
            :value="number_format($stats['members'])"
            :hint="'Tersebar di ' . $stats['teams'] . ' tim, termasuk Anda'"
        />

        <x-stat-card
            label="Rata-rata skor"
            :value="$stats['avg_score'] === null ? '—' : number_format($stats['avg_score'], 1)"
            :hint="'Dari ' . number_format($stats['assessments']) . ' assessment selesai · '
                . number_format($stats['zero_tolerance']) . ' pelanggaran ZTP'"
            :tone="$stats['avg_score'] !== null && $stats['avg_score'] < 70 ? 'danger' : 'default'"
        />

        <x-stat-card
            label="Menunggu diperiksa"
            :value="number_format($stats['pending_review'])"
            hint="Assessment terkirim yang belum Anda periksa"
            :href="route('qa.sampling.report.index', ['status' => 'submitted'])"
            :tone="$stats['pending_review'] > 0 ? 'alert' : 'calm'"
        />

        <x-stat-card
            label="Ujian belum dinilai"
            :value="number_format($stats['exam_queue'])"
            hint="Jawaban anggota tim yang menunggu penilaian"
            :href="route('qa.exam.grading.index', ['status' => 'submitted'])"
            :tone="$stats['exam_queue'] > 0 ? 'alert' : 'calm'"
        />
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-panel
            class="lg:col-span-2"
            title="Perlu Pembinaan"
            :description="'Rata-rata skor anggota tim dalam ' . $windowDays . ' hari terakhir, terendah lebih dulu.'"
            :href="route('qa.sampling.report.scores')"
            link-label="Lihat skor per agent"
        >
            @if ($coaching->isEmpty())
                {{-- Konvensi tabel di aplikasi ini: baris `colspan`, bukan kotak
                     dashed di dalam sel. --}}
                <p class="px-5 py-8 text-center text-sm text-ink-400">
                    Belum ada assessment selesai pada periode ini, jadi belum ada yang bisa dibina.
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-ink-100 bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                            <tr>
                                <th class="px-5 py-3 font-semibold sm:px-6">Anggota</th>
                                <th class="px-5 py-3 text-right font-semibold">Dinilai</th>
                                <th class="px-5 py-3 text-right font-semibold">Rata-rata</th>
                                <th class="px-5 py-3 text-right font-semibold sm:px-6">ZTP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            @foreach ($coaching as $row)
                                <tr class="hover:bg-ink-50/60">
                                    <td class="px-5 py-3 sm:px-6">
                                        <p class="font-medium text-ink-900">{{ $row['name'] }}</p>
                                        <p class="text-xs text-ink-400">
                                            Terakhir dinilai
                                            {{ \Illuminate\Support\Carbon::parse($row['last_date'])->format('d M Y') }}
                                        </p>
                                    </td>
                                    <td class="px-5 py-3 text-right tabular-nums text-ink-600">{{ number_format($row['total']) }}</td>
                                    <td class="px-5 py-3">
                                        <div class="flex items-center justify-end gap-2">
                                            <x-score-bar :value="$row['avg_score']" :threshold="70" class="w-16" />

                                            <span class="w-10 shrink-0 text-right font-semibold tabular-nums {{ $row['avg_score'] < 70 ? 'text-red-600' : 'text-ink-900' }}">
                                                {{ number_format($row['avg_score'], 1) }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 text-right sm:px-6">
                                        @if ($row['zero_tolerance'] > 0)
                                            <x-badge variant="red">{{ $row['zero_tolerance'] }}</x-badge>
                                        @else
                                            <span class="text-ink-300">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-panel>

        {{-- Dua antrean, satu panel. Judul bagian sekaligus menjadi tautan ke
             daftar lengkapnya, sehingga angka di kartu atas bisa langsung
             ditindaklanjuti tanpa menambah tautan lain di halaman. --}}
        <x-panel title="Antrean Tim" description="Semua yang belum selesai, tanpa batas periode.">
            <a href="{{ route('qa.sampling.report.index', ['status' => 'submitted']) }}"
               class="group flex items-center justify-between gap-3 border-b border-ink-100 bg-ink-50/60 px-5 py-2.5 transition-colors hover:bg-ink-100/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-500 sm:px-6">
                <span class="text-xs font-semibold uppercase tracking-wide text-ink-500">Menunggu Diperiksa</span>

                <span class="flex items-center gap-1 text-xs font-semibold tabular-nums text-brand-700">
                    {{ number_format($stats['pending_review']) }}
                    <svg class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m9 6 6 6-6 6" />
                    </svg>
                </span>
            </a>

            <div class="divide-y divide-ink-100">
                @forelse ($pending as $assessment)
                    <x-list-row
                        :href="route('qa.sampling.show', $assessment)"
                        :title="$assessment->employee?->name ?? $assessment->employee_name_snapshot"
                        :meta="($assessment->period ?? 'Tanpa periode') . ' · dikirim ' . ($assessment->submitted_at?->diffForHumans() ?? '—')"
                    >
                        <x-badge variant="amber">Terkirim</x-badge>
                    </x-list-row>
                @empty
                    <p class="px-5 py-4 text-sm text-ink-400">Tidak ada assessment yang menunggu diperiksa.</p>
                @endforelse
            </div>

            <a href="{{ route('qa.exam.grading.index', ['status' => 'submitted']) }}"
               class="group flex items-center justify-between gap-3 border-y border-ink-100 bg-ink-50/60 px-5 py-2.5 transition-colors hover:bg-ink-100/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-500 sm:px-6">
                <span class="text-xs font-semibold uppercase tracking-wide text-ink-500">Ujian Belum Dinilai</span>

                <span class="flex items-center gap-1 text-xs font-semibold tabular-nums text-brand-700">
                    {{ number_format($stats['exam_queue']) }}
                    <svg class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m9 6 6 6-6 6" />
                    </svg>
                </span>
            </a>

            <div class="divide-y divide-ink-100">
                @forelse ($examQueue as $assignment)
                    {{-- Tanpa tautan: penilaian ujian adalah wewenang QA
                         (`ExamAssignmentPolicy::grade`), jadi tautan ke halaman
                         penilaian hanya akan berakhir 403 bagi supervisor. --}}
                    <x-list-row
                        :title="$assignment->agent?->name ?? 'Agent'"
                        :meta="($assignment->form?->title ?? 'Ujian') . ' · attempt ke-' . $assignment->attempt_ke"
                    >
                        <x-badge variant="amber">{{ $assignment->completed_at?->diffForHumans() ?? 'Menunggu' }}</x-badge>
                    </x-list-row>
                @empty
                    <p class="px-5 py-4 text-sm text-ink-400">Tidak ada ujian yang menunggu penilaian.</p>
                @endforelse
            </div>

            <p class="border-t border-ink-100 px-5 py-3 text-xs text-ink-400 sm:px-6">
                Penilaian ujian dikerjakan QA — daftar ini untuk memantau anggota tim.
            </p>
        </x-panel>
    </div>
@endsection
