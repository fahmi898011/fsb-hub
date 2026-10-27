@extends('layouts.app')

@section('title', 'Pencarian')

@section('content')
    {{--
        PRD §13 — Search adalah fitur utama, bukan input dekoratif.
        Mendukung Ctrl/⌘+K, filter kategori, recent search, dan jelajah kategori.
    --}}
    <div
        x-data="searchPage({
            term: @js($term),
            category: @js($filters['category'] ?? ''),
            categories: @js($categories->map(fn ($c) => ['name' => $c->name, 'slug' => $c->slug])->values()),
        })"
        x-init="init()"
        class="mx-auto max-w-3xl"
    >
        <header class="mb-5">
            <h1 class="text-xl font-semibold tracking-tight text-ink-900 sm:text-2xl">Pencarian</h1>
            <p class="mt-1 text-sm text-ink-500">Telusuri judul, isi, nomor dokumen, kategori, dan tag.</p>
        </header>

        {{-- Kolom pencarian — filter kategori dipertahankan saat submit ulang --}}
        <x-search-bar
            :value="$term"
            :hidden="['category' => $filters['category'] ?? null]"
            placeholder="Mis. SOP cuti, JUK-001, Juknis..."
            shortcut
            class="w-full"
        />

        {{-- Filter kategori --}}
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <span class="mr-1 text-xs font-medium uppercase tracking-wide text-ink-400">Kategori</span>
            <a href="{{ route('search', array_filter(['q' => $term, 'category' => null])) }}"
               class="badge {{ empty($filters['category'] ?? null) ? 'badge-brand' : 'badge-gray' }} px-3 py-1.5">Semua</a>
            @foreach ($categories as $category)
                <a href="{{ route('search', array_filter(['q' => $term, 'category' => $category->slug])) }}"
                   class="badge {{ ($filters['category'] ?? null) === $category->slug ? 'badge-brand' : 'badge-gray' }} px-3 py-1.5">{{ $category->name }}</a>
            @endforeach
        </div>

        {{-- Recent search — PRD §13 (disimpan lokal, tanpa backend baru) --}}
        <div x-show="recent.length > 0" x-cloak class="mt-4 flex flex-wrap items-center gap-2 text-sm">
            <span class="inline-flex items-center gap-1 text-xs text-ink-400">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 2M4 12a8 8 0 1 0 8-8 8 8 0 0 0-8 8Z" />
                </svg>
                Terakhir dicari
            </span>
            <template x-for="entry in recent" :key="entry">
                <a :href="`{{ route('search') }}?q=${encodeURIComponent(entry)}`"
                   class="rounded-full border border-ink-200 bg-white px-3 py-1 text-xs text-ink-600 transition-colors hover:border-ink-300 hover:text-ink-900"
                   x-text="entry"></a>
            </template>
            <button type="button" @click="clear()" class="text-xs text-ink-400 underline-offset-2 hover:text-ink-700 hover:underline">Hapus</button>
        </div>

        <div class="mt-6">
            @if ($term === '' && empty($filters['category'] ?? null))
                @if ($categories->isNotEmpty())
                    <section>
                        <h2 class="mb-3 text-sm font-semibold text-ink-900">Jelajahi kategori</h2>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            @foreach ($categories as $category)
                                <x-category-card :category="$category" />
                            @endforeach
                        </div>
                    </section>
                @else
                    <x-empty-state title="Mulai pencarian" description="Masukkan kata kunci untuk menemukan informasi." />
                @endif
            @elseif (! $results || $results->isEmpty())
                <x-empty-state
                    title="Tidak ada hasil untuk &ldquo;{{ $term }}&rdquo;"
                    description="Coba kata kunci lain atau jelajahi kategori di bawah."
                >
                    <div class="mt-2 flex flex-wrap justify-center gap-2">
                        @foreach ($categories as $category)
                            <a href="{{ route('information.category', $category->slug) }}" class="badge-gray px-3 py-1.5">{{ $category->name }}</a>
                        @endforeach
                    </div>
                </x-empty-state>
            @else
                <p class="mb-4 text-sm text-ink-500">
                    Menampilkan <span class="font-medium text-ink-800">{{ $results->total() }}</span> hasil
                    @if ($term !== '')
                        untuk <span class="font-medium text-ink-800">&ldquo;{{ $term }}&rdquo;</span>
                    @endif
                    @if ($activeCategory = $categories->firstWhere('slug', $filters['category'] ?? null))
                        di kategori <span class="font-medium text-ink-800">{{ $activeCategory->name }}</span>
                    @endif
                    .
                </p>

                <div class="doc-list">
                    @foreach ($results as $item)
                        <x-information-card :item="$item" />
                    @endforeach
                </div>

                <div class="mt-6">
                    {{ $results->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function searchPage(config) {
        return {
            term: config.term,
            recent: [],

            init() {
                try {
                    this.recent = JSON.parse(localStorage.getItem('fsb-recent-search') || '[]');
                } catch (e) {
                    this.recent = [];
                }

                if (this.term && this.term.length > 1) {
                    this.remember(this.term);
                }
            },

            remember(value) {
                const next = [value, ...this.recent.filter((item) => item !== value)].slice(0, 5);
                this.recent = next;
                localStorage.setItem('fsb-recent-search', JSON.stringify(next));
            },

            clear() {
                this.recent = [];
                localStorage.removeItem('fsb-recent-search');
            },
        };
    }
</script>
@endpush