@props([
    'action' => null,
    'name' => 'q',
    'value' => null,
    'placeholder' => 'Cari informasi...',
    'size' => 'md',
    'shortcut' => false,
    'hidden' => [],
])

@php
$action = $action ?? route('search');
$value = $value ?? request($name);
$sizeClass = $size === 'lg' ? 'py-3 pl-11 text-base' : 'py-2 pl-10 text-sm';
$iconClass = $size === 'lg' ? 'left-3.5 h-5 w-5' : 'left-3 h-4 w-4';
$shortcutClass = $size === 'lg' ? 'right-3' : 'right-2.5';
@endphp

{{--
    PRD §13 — pencarian adalah pintu masuk utama.
    Mendukung pintasan keyboard Ctrl/⌘ + K untuk fokus instan.
--}}
<form
    action="{{ $action }}"
    method="GET"
    @if ($shortcut) x-data x-ref="searchForm" @endif
    {{ $attributes }}
>
    @foreach ($hidden as $hiddenName => $hiddenValue)
        @if (filled($hiddenValue))
            <input type="hidden" name="{{ $hiddenName }}" value="{{ $hiddenValue }}">
        @endif
    @endforeach

    <label class="relative block">
        <span class="pointer-events-none absolute inset-y-0 my-auto flex items-center text-ink-400 {{ $iconClass }}">
            <svg class="h-full w-full" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.3-4.3M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14Z" />
            </svg>
        </span>

        <input
            type="search"
            name="{{ $name }}"
            value="{{ $value }}"
            placeholder="{{ $placeholder }}"
            @if ($shortcut) x-ref="searchInput" @endif
            class="form-input {{ $sizeClass }} {{ $shortcut ? 'pr-20' : '' }}"
            autocomplete="off"
        >

        @if ($shortcut)
            <kbd class="pointer-events-none absolute inset-y-0 my-auto hidden h-6 items-center gap-0.5 rounded-md border border-ink-200 bg-ink-50 px-1.5 text-[11px] font-medium text-ink-500 sm:flex {{ $shortcutClass }}">
                <span class="text-xs">⌘</span>K
            </kbd>
        @endif
    </label>

    @if ($shortcut)
        <script>
            (function () {
                var el = document.currentScript.previousElementSibling.querySelector('[x-ref="searchInput"]');
                window.addEventListener('keydown', function (event) {
                    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                        event.preventDefault();
                        el.focus();
                        el.select();
                    }
                });
            })();
        </script>
    @endif
</form>
