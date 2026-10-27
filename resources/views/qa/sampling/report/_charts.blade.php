{{-- Skrip pembantu grafik ApexCharts untuk halaman laporan.
     AGENT: asisten | 2026-10-04 | buat berkas baru | modul KPI, fase 3 QA reporting

     Diletakkan di @push('scripts') oleh halaman yang memakai grafik. Yang
     perlu disiapkan halaman: (1) elemen ber-id, (2) JSON data di
     `data-chart-*`. Semua grafik membaca warna dari variabel CSS yang sama
     supaya tampilan tetap seragam bila tema berubah. --}}
<script>
    (function () {
        const ink = '#64748b';
        const grid = '#e2e8f0';

        const base = {
            chart: { fontFamily: 'Inter, sans-serif', toolbar: { show: false }, animations: { speed: 300 } },
            dataLabels: { enabled: false },
            grid: { borderColor: grid, strokeDashArray: 4 },
            tooltip: { theme: 'light' },
        };

        window.renderReportCharts = function () {
            if (typeof window.ApexCharts === 'undefined') {
                // Bundel belum selesai dimuat (mis. koneksi lambat). Halaman
                // tetap terbaca — angka pentingnya sudah ada sebagai tabel,
                // jadi grafik yang gagal tampil bukan kegagalan data.
                return;
            }

            document.querySelectorAll('[data-chart]').forEach(function (el) {
                const series = JSON.parse(el.dataset.chartSeries || '[]');
                const categories = JSON.parse(el.dataset.chartCategories || '[]');
                const type = el.dataset.chart;

                const options = Object.assign({}, base, {
                    series: series,
                    chart: Object.assign({}, base.chart, { type: type, height: Number(el.dataset.chartHeight || 300) }),
                    labels: categories,
                    xaxis: { categories: categories, labels: { style: { colors: ink } } },
                    yaxis: { labels: { style: { colors: ink } } },
                    legend: { position: 'bottom', labels: { colors: ink } },
                    colors: ['#2563eb', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6'],
                });

                if (type === 'line' || type === 'area') {
                    options.stroke = { curve: 'smooth', width: 2 };
                }

                new window.ApexCharts(el, options).render();
            });
        };

        document.addEventListener('DOMContentLoaded', window.renderReportCharts);
    })();
</script>
