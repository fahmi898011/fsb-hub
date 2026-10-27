<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $reportTitle ?? 'Laporan' }}</title>
    {{--
        Kerangka semua laporan PDF.

        AGENT: asisten | 2026-10-16 | buat berkas baru | modul Ekspor PDF

        Ditulis dengan CSS biasa, BUKAN kelas Tailwind: dompdf tidak memuat
        berkas CSS hasil build Vite, jadi kelas seperti `text-ink-500` tidak akan
        berpengaruh sama sekali di sini.

        `thead { display: table-header-group }` membuat judul kolom diulang di
        setiap halaman — tanpa itu, halaman kedua dan seterusnya berisi angka
        tanpa keterangan kolom.
    --}}
    <style>
        @page {
            margin: 26px 28px 48px 28px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 8.6pt;
            line-height: 1.35;
            color: #1f2937;
            margin: 0;
        }

        .header {
            border-bottom: 2px solid #1d4ed8;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }

        .brand {
            font-size: 7.2pt;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #64748b;
        }

        h1 {
            font-size: 14pt;
            margin: 2px 0 0;
            color: #0f172a;
        }

        .meta {
            font-size: 7.4pt;
            color: #64748b;
            margin-top: 3px;
        }

        .filters {
            margin: 0 0 12px;
            font-size: 7.8pt;
            color: #334155;
        }

        .filters span {
            margin-right: 12px;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
        }

        table.data thead {
            display: table-header-group;
        }

        table.data th {
            background: #eef2ff;
            color: #1e293b;
            font-size: 7.4pt;
            text-transform: uppercase;
            letter-spacing: .4px;
            text-align: left;
            padding: 5px 6px;
            border-bottom: 1px solid #c7d2fe;
        }

        table.data td {
            padding: 4.5px 6px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }

        table.data tbody tr:nth-child(even) td {
            background: #f8fafc;
        }

        .num {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .muted {
            color: #64748b;
        }

        .strong {
            font-weight: bold;
            color: #0f172a;
        }

        table.stats {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px 0;
            margin: 0 -6px 12px;
        }

        table.stats td {
            width: 25%;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 7px 9px;
            vertical-align: top;
        }

        .stat-label {
            font-size: 7pt;
            text-transform: uppercase;
            letter-spacing: .3px;
            color: #64748b;
        }

        .stat-value {
            font-size: 13pt;
            font-weight: bold;
            color: #0f172a;
        }

        .section-title {
            font-size: 10pt;
            font-weight: bold;
            margin: 14px 0 6px;
            color: #0f172a;
        }

        .empty {
            padding: 16px;
            text-align: center;
            color: #94a3b8;
            border: 1px dashed #cbd5e1;
            border-radius: 6px;
        }

        .note {
            margin-top: 10px;
            font-size: 7.4pt;
            color: #64748b;
        }

        .good { color: #047857; }
        .bad { color: #b91c1c; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">Pusat Informasi &middot; Quality Assurance</div>
        <h1>{{ $reportTitle }}</h1>
        <div class="meta">
            Dicetak {{ $generatedAt->format('d/m/Y H:i') }} WIB
            @isset($filterSummary)
                &middot; {{ $filterSummary }}
            @endisset
        </div>
    </div>

    @yield('content')
</body>
</html>
