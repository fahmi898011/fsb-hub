{{-- Sub-navigasi laporan QA Sampling (PRD Employee KPI/QA §39-§42). --}}
{{-- AGENT: asisten | 2026-10-04 | buat berkas baru | modul KPI, fase 3 QA reporting --}}
@php($reportTabs = [
    ['route' => 'qa.sampling.report.dashboard', 'pattern' => 'qa.sampling.report.dashboard', 'label' => 'Dashboard'],
    ['route' => 'qa.sampling.report.scores', 'pattern' => 'qa.sampling.report.scores*', 'label' => 'Skor QA per Agent'],
    ['route' => 'qa.sampling.report.index', 'pattern' => 'qa.sampling.report.index', 'label' => 'Laporan Assessment'],
    ['route' => 'qa.sampling.report.parameters', 'pattern' => 'qa.sampling.report.parameters', 'label' => 'Analitik Parameter'],
    ['route' => 'qa.sampling.report.employees.index', 'pattern' => 'qa.sampling.report.employees.*', 'label' => 'Riwayat Karyawan'],
])

<nav class="mb-5 flex flex-wrap gap-2 border-b border-ink-100 pb-3">
    @foreach ($reportTabs as $tab)
        <a href="{{ route($tab['route']) }}"
           class="{{ request()->routeIs($tab['pattern']) ? 'btn-primary btn-sm' : 'btn-ghost btn-sm text-ink-600' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
