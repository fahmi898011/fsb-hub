@props(['category'])

@php
$color = $category->color ?: '#3b82f6';
@endphp

<a href="{{ route('information.category', $category->slug) }}" class="category-card group" style="--cat-color: {{ $color }};">
    <span class="category-icon" style="color: {{ $color }};">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16M4 12h16M4 19h10" />
        </svg>
    </span>

    <div class="min-w-0 flex-1">
        <p class="truncate font-semibold text-ink-900 group-hover:text-brand-700">{{ $category->name }}</p>
        <p class="mt-0.5 line-clamp-2 text-sm leading-relaxed text-ink-500">{{ $category->description }}</p>
    </div>

    @if (isset($category->items_count))
        <div class="mt-auto flex items-center justify-between border-t border-ink-100 pt-3">
            <span class="text-xs font-medium text-ink-500">{{ $category->items_count }} dokumen</span>
            <svg class="h-4 w-4 text-ink-400 transition-transform duration-150 group-hover:translate-x-0.5 group-hover:text-brand-600"
                 fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </div>
    @endif
</a>