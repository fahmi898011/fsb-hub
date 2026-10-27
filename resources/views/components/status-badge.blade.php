@props(['status'])

@php
$map = [
    'published' => ['green', 'Dipublikasikan'],
    'draft' => ['amber', 'Draft'],
    'archived' => ['gray', 'Diarsipkan'],
];
[$variant, $label] = $map[$status] ?? ['gray', ucfirst((string) $status)];
@endphp

<x-badge :variant="$variant">{{ $label }}</x-badge>