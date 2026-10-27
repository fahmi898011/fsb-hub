@props(['variant' => 'gray'])

@php
$classes = [
    'brand' => 'badge-brand',
    'gray' => 'badge-gray',
    'green' => 'badge-green',
    'amber' => 'badge-amber',
    'red' => 'badge-red',
][$variant] ?? 'badge-gray';
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</span>