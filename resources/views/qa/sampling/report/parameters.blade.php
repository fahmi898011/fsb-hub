@extends('layouts.app')

{{-- Analitik Parameter (PRD Employee KPI/QA §41). --}}
{{-- AGENT: asisten | 2026-10-04 | buat berkas baru | modul KPI, fase 3 QA reporting --}}

@section('title', 'Analitik Parameter QA Sampling')

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-semibold text-ink-900">Analitik Parameter</h1>
        <p class="text-sm text-ink-500">
            Parameter mana yang paling sering mendapat NO dan seberapa besar penaltinya (PRD §41).
        </p>
    </div>

    @include('qa.sampling.report._nav')

    @include('qa.sampling.report._filters')

    {{-- Catatan makna kolom: parameter ZTP bersifat terbalik — yang dihitung
         sebagai "NO" adalah jawaban YES ("apakah terjadi pelanggaran?").
         Tanpa penjelasan ini, tabel akan tampak tidak konsisten bagi pembaca
         yang tahu parameternya berbentuk pertanyaan terbalik. --}}
    <div class="mb-4 rounded-xl border border-ink-100 bg-ink-50/70 px-4 py-3 text-xs text-ink-500">
        Kolom <strong>NO</strong> menghitung item yang dianggap <em>pelanggaran</em>. Untuk parameter
        Zero Tolerance yang berbentuk pertanyaan terbalik, jawaban <strong>YES</strong> yang dihitung sebagai pelanggaran.
        <strong>NO Rate</strong> dihitung hanya dari item yang sudah dijawab (bukan termasuk yang belum dinilai).
    </div>

    @if ($parameters->isEmpty())
        <x-empty-state title="Belum ada data parameter"
                       description="Analitik muncul setelah ada assessment berstatus terkirim, diperiksa, atau terkunci pada rentang ini." />
    @else
        <div class="card overflow-hidden">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-ink-100 bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Parameter</th>
                        <th class="px-5 py-3 text-right font-semibold">Dinilai</th>
                        <th class="px-5 py-3 text-right font-semibold">YES</th>
                        <th class="px-5 py-3 text-right font-semibold">NO</th>
                        <th class="px-5 py-3 text-right font-semibold">NA</th>
                        <th class="px-5 py-3 text-right font-semibold">NO Rate</th>
                        <th class="px-5 py-3 text-right font-semibold">Total Penalti</th>
                        <th class="px-5 py-3 text-right font-semibold">Rata Penalti</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @foreach ($parameters as $row)
                        <tr class="hover:bg-ink-50">
                            <td class="px-5 py-3">
                                <p class="font-medium text-ink-800">{{ $row['parameter'] }}</p>
                                <p class="text-xs text-ink-400">{{ $row['category'] }}</p>
                            </td>
                            <td class="px-5 py-3 text-right text-ink-500">{{ number_format($row['total']) }}</td>
                            <td class="px-5 py-3 text-right text-ink-500">{{ number_format($row['yes_count']) }}</td>
                            <td class="px-5 py-3 text-right text-ink-700">{{ number_format($row['no_count']) }}</td>
                            <td class="px-5 py-3 text-right text-ink-400">{{ number_format($row['na_count']) }}</td>
                            <td class="px-5 py-3 text-right font-medium {{ $row['no_rate'] > 20 ? 'text-red-600' : 'text-ink-900' }}">
                                {{ number_format($row['no_rate'], 2) }}%
                            </td>
                            <td class="px-5 py-3 text-right text-ink-700">{{ number_format($row['total_penalty'], 2) }}</td>
                            <td class="px-5 py-3 text-right text-ink-500">{{ number_format($row['avg_penalty'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
