{{-- Sub-navigasi area master KPI & Scorecard (dipakai bersama halaman admin.kpi.*). --}}
{{-- AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master --}}
@php($tabs = [
    ['route' => 'admin.kpi.scorecards.index', 'pattern' => 'admin.kpi.scorecards.*', 'label' => 'Scorecard'],
    ['route' => 'admin.kpi.kpis.index', 'pattern' => 'admin.kpi.kpis.*', 'label' => 'KPI'],
    ['route' => 'admin.kpi.channels.index', 'pattern' => 'admin.kpi.channels.*', 'label' => 'Channel'],
    // Target Sampling diletakkan paling kanan: ia mengatur ANGKA PEMBANDING
    // laporan, bukan isi scorecard. Menaruhnya setelah master isi menjaga
    // urutan "bentuk penilaian -> aturan capaian".
    ['route' => 'admin.kpi.qa-targets.index', 'pattern' => 'admin.kpi.qa-targets.*', 'label' => 'Target Sampling'],
])

<nav class="mb-5 flex flex-wrap gap-2 border-b border-ink-100 pb-3">
    @foreach ($tabs as $tab)
        <a href="{{ route($tab['route']) }}"
           class="{{ request()->routeIs($tab['pattern']) ? 'btn-primary btn-sm' : 'btn-ghost btn-sm text-ink-600' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
