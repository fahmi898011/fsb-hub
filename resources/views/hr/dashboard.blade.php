@extends('layouts.app')

{{-- Halaman muka HR: agregat per divisi untuk penilaian kinerja lintas divisi.
     Tidak ada nama karyawan di halaman ini — lihat docblock controller. --}}
{{-- AGENT: asisten | 2026-10-20 | buat berkas baru | modul Dashboard per peran --}}
{{-- AGENT: asisten | 2026-10-20 | rombak tata letak: meter skor divisi, parameter selebar penuh | UI/UX --}}

@section('title', 'Dashboard HR')

@section('content')
    {{-- Topbar sudah merender judul halaman; di sini cukup konteks + aksi. --}}
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <p class="text-sm text-ink-500">
                Ringkasan {{ $windowDays }} hari terakhir per divisi.
            </p>
            <p class="mt-1 text-xs text-ink-400">
                Halaman ini sengaja tidak menampilkan nama karyawan — daftar nama beserta skornya ada di laporan QA.
            </p>
        </div>

        <a href="{{ route('qa.sampling.report.index') }}" class="btn-primary shrink-0">Buka Laporan QA</a>
    </div>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
        <x-stat-card
            label="Karyawan"
            :value="number_format($stats['employees'])"
            :hint="'Tersebar di ' . number_format($stats['divisions']) . ' divisi'"
        />

        <x-stat-card
            label="Tim"
            :value="number_format($stats['teams'])"
            hint="Tim kerja di seluruh divisi"
            :href="route('admin.teams.index')"
        />

        <x-stat-card
            label="Rata-rata skor"
            :value="$stats['avg_score'] === null ? '—' : number_format($stats['avg_score'], 1)"
            :hint="'Dari ' . number_format($stats['assessments']) . ' assessment selesai'"
            :tone="$stats['avg_score'] !== null && $stats['avg_score'] < 70 ? 'danger' : 'default'"
        />

        <x-stat-card
            label="Pelanggaran ZTP"
            :value="number_format($stats['zero_tolerance'])"
            :hint="'Terpicu dalam ' . $windowDays . ' hari terakhir'"
            :tone="$stats['zero_tolerance'] > 0 ? 'danger' : 'calm'"
        />
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-panel
            class="lg:col-span-2"
            title="Capaian per Divisi"
            :description="'Assessment selesai dalam ' . $windowDays . ' hari terakhir.'"
        >
            @if ($divisions->isEmpty())
                <p class="px-5 py-8 text-center text-sm text-ink-400">
                    Belum ada divisi. Divisi yang dibuat Admin akan muncul di sini beserta capaiannya.
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-ink-100 bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                            <tr>
                                <th class="px-5 py-3 font-semibold sm:px-6">Divisi</th>
                                <th class="px-5 py-3 text-right font-semibold">Karyawan</th>
                                <th class="px-5 py-3 text-right font-semibold">Dinilai</th>
                                <th class="px-5 py-3 text-right font-semibold">Rata-rata</th>
                                <th class="px-5 py-3 text-right font-semibold sm:px-6">ZTP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            @foreach ($divisions as $division)
                                <tr class="hover:bg-ink-50/60">
                                    <td class="px-5 py-3 sm:px-6">
                                        <p class="font-medium text-ink-900">{{ $division['label'] }}</p>
                                        <p class="text-xs text-ink-400">{{ $division['teams'] }} tim</p>
                                    </td>
                                    <td class="px-5 py-3 text-right tabular-nums text-ink-600">{{ number_format($division['employees']) }}</td>
                                    <td class="px-5 py-3 text-right tabular-nums text-ink-600">{{ number_format($division['assessments']) }}</td>
                                    <td class="px-5 py-3">
                                        @if ($division['avg_score'] === null)
                                            {{-- Dibedakan dari skor 0: divisi ini memang belum
                                                 punya assessment pada periode tersebut. --}}
                                            <p class="text-right text-xs text-ink-400">Belum dinilai</p>
                                        @else
                                            <div class="flex items-center justify-end gap-2">
                                                <x-score-bar :value="$division['avg_score']" :threshold="70" class="w-16" />

                                                <span class="w-10 shrink-0 text-right font-semibold tabular-nums {{ $division['avg_score'] < 70 ? 'text-red-600' : 'text-ink-900' }}">
                                                    {{ number_format($division['avg_score'], 1) }}
                                                </span>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right sm:px-6">
                                        @if ($division['zero_tolerance'] > 0)
                                            <x-badge variant="red">{{ $division['zero_tolerance'] }}</x-badge>
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

        <x-panel
            title="Status Assessment"
            description="Seluruh divisi pada periode yang sama."
            :href="route('qa.sampling.report.index')"
            link-label="Lihat rincian"
        >
            <div class="divide-y divide-ink-100">
                @foreach ($statusCounts as $status => $count)
                    <x-list-row
                        :href="route('qa.sampling.report.index', ['status' => $status])"
                        :title="$statusLabels[$status] ?? ucfirst($status)"
                        :meta="$status === 'submitted' ? 'Menunggu diperiksa reviewer' : null"
                    >
                        <span class="text-sm font-semibold tabular-nums text-ink-900">{{ number_format($count) }}</span>
                    </x-list-row>
                @endforeach
            </div>
        </x-panel>
    </div>

    {{-- Tabel parameter memakai lebar penuh: lima kolom angka di dalam 2/3
         lebar membuat header dan angka saling berdesakan. --}}
    <div class="mt-6">
        <x-panel
            title="Parameter Paling Sering Gagal"
            :description="'Diurutkan dari jumlah pelanggaran terbanyak dalam ' . $windowDays . ' hari terakhir.'"
            :href="route('qa.sampling.report.parameters')"
            link-label="Buka analitik"
        >
            @if ($parameters->isEmpty())
                <p class="px-5 py-8 text-center text-sm text-ink-400">
                    Analitik parameter muncul setelah ada assessment yang selesai pada periode ini.
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-ink-100 bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                            <tr>
                                <th class="px-5 py-3 font-semibold sm:px-6">Parameter</th>
                                <th class="px-5 py-3 text-right font-semibold">Dijawab</th>
                                <th class="px-5 py-3 text-right font-semibold">Pelanggaran</th>
                                <th class="px-5 py-3 font-semibold">NO Rate</th>
                                <th class="px-5 py-3 text-right font-semibold sm:px-6">Rata penalty</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            @foreach ($parameters as $parameter)
                                <tr class="hover:bg-ink-50/60">
                                    <td class="px-5 py-3 sm:px-6">
                                        <p class="font-medium text-ink-900">{{ $parameter['parameter'] }}</p>
                                        <p class="text-xs text-ink-400">{{ $parameter['category'] }}</p>
                                    </td>
                                    <td class="px-5 py-3 text-right tabular-nums text-ink-600">{{ number_format($parameter['answered']) }}</td>
                                    <td class="px-5 py-3 text-right tabular-nums text-ink-600">{{ number_format($parameter['no_count']) }}</td>
                                    <td class="px-5 py-3">
                                        {{-- Ambang 10% mengikuti penyorotan yang sudah dipakai
                                             di laporan: di atas itu parameter dianggap masalah. --}}
                                        <div class="flex items-center gap-2">
                                            <x-score-bar
                                                :value="$parameter['no_rate']"
                                                :tone="$parameter['no_rate'] >= 10 ? 'danger' : 'default'"
                                                class="w-24"
                                            />

                                            <span class="w-12 shrink-0 tabular-nums {{ $parameter['no_rate'] >= 10 ? 'font-semibold text-red-600' : 'text-ink-600' }}">
                                                {{ number_format($parameter['no_rate'], 1) }}%
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 text-right tabular-nums text-ink-600 sm:px-6">{{ number_format($parameter['avg_penalty'], 1) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-panel>
    </div>
@endsection
