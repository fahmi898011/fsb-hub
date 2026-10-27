{{-- Badge prioritas tiket. Urgensinya dinaikkan lewat warna: merah untuk
     Mendesak, amber untuk Tinggi, sisanya netral. --}}
@props(['priority'])

@php
$map = [
    'low' => ['gray', 'Rendah'],
    'medium' => ['brand', 'Sedang'],
    'high' => ['amber', 'Tinggi'],
    'urgent' => ['red', 'Mendesak'],
];
[$variant, $label] = $map[$priority] ?? ['gray', ucfirst((string) $priority)];
@endphp

<x-badge :variant="$variant">{{ $label }}</x-badge>
