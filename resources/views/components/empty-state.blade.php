@props(['title' => 'Tidak ada data', 'description' => null])

<div {{ $attributes->merge(['class' => 'empty-state']) }}>
    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-ink-100 text-ink-400">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
        </svg>
    </div>
    <p class="text-base font-medium text-ink-800">{{ $title }}</p>
    @if ($description)
        <p class="max-w-md text-sm text-ink-500">{{ $description }}</p>
    @endif

    @if (! $slot->isEmpty())
        <div class="mt-1">{{ $slot }}</div>
    @endif
</div>