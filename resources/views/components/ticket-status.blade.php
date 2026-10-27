{{-- Badge status tiket. Pemetaan warna sengaja terpusat di sini supaya
     daftar tiket, detail karyawan, dan antrean IT memakai bahasa visual yang
     sama. Label mengikuti accessor Ticket::getStatusLabelAttribute(). --}}
@props(['status'])

@php
$map = [
    'open' => ['brand', 'Terbuka'],
    'in_progress' => ['amber', 'Diproses'],
    'resolved' => ['green', 'Selesai'],
    'closed' => ['gray', 'Ditutup'],
];
[$variant, $label] = $map[$status] ?? ['gray', ucfirst((string) $status)];
@endphp

<x-badge :variant="$variant">{{ $label }}</x-badge>
