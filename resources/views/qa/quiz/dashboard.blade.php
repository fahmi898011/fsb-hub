@extends('layouts.app')
@section('title', 'Dashboard Quiz')
@section('content')
    <div class="mb-4"><h1 class="text-xl font-semibold text-ink-900">Dashboard Quiz</h1><p class="text-sm text-ink-500">Pantau template, partisipasi, dan hasil quiz.</p></div>
    @include('qa.quiz._nav')
    <div class="mb-5 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-report-stat label="Template Quiz" :value="number_format($stats['templates'])" />
        <x-report-stat label="Template Aktif" :value="number_format($stats['active'])" />
        <x-report-stat label="Total Attempt" :value="number_format($stats['attempts'])" />
        <x-report-stat label="Rata-rata Skor" :value="$stats['average'] === null ? '—' : number_format($stats['average'], 2).'%'" />
    </div>
    <div class="card overflow-hidden">
        <div class="border-b border-ink-100 px-5 py-3"><h2 class="text-sm font-semibold text-ink-800">Aktivitas Quiz Terbaru</h2></div>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-ink-50 text-xs uppercase text-ink-500"><tr><th class="px-5 py-3">Quiz</th><th class="px-5 py-3">Karyawan</th><th class="px-5 py-3">Skor</th><th class="px-5 py-3">Hasil</th><th class="px-5 py-3">Tanggal</th></tr></thead><tbody class="divide-y divide-ink-100">
            @forelse ($recent as $attempt)<tr><td class="px-5 py-3">{{ $attempt->quiz_name_snapshot }}</td><td class="px-5 py-3">{{ $attempt->employee?->name ?? '—' }}</td><td class="px-5 py-3">{{ $attempt->score === null ? '—' : number_format($attempt->score, 2).'%' }}</td><td class="px-5 py-3">{{ $attempt->result_label }}</td><td class="px-5 py-3">{{ $attempt->created_at?->format('d/m/Y H:i') }}</td></tr>@empty<tr><td colspan="5" class="px-5 py-8 text-center text-ink-400">Belum ada pengerjaan quiz.</td></tr>@endforelse
        </tbody></table></div>
    </div>
@endsection