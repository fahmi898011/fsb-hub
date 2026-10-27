import './bootstrap';

import Alpine from 'alpinejs';
import ApexCharts from 'apexcharts';

window.Alpine = Alpine;

// ApexCharts dipakai halaman laporan KPI (PRD Employee KPI/QA §39-§42).
// Didaftarkan ke window — bukan diimpor langsung di dalam Blade — supaya
// grafik hanya diunduh sekali bersama bundel (bukan script eksternal per
// halaman), dan agar halaman yang tidak menampilkan grafik tidak memuatnya.
// AGENT: asisten | 2026-10-04 | daftarkan ApexCharts | modul KPI, fase 3 QA reporting
window.ApexCharts = ApexCharts;

Alpine.start();
