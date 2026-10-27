@extends('layouts.app')

{{-- AGENT: asisten | 2026-10-06 | tambah tautan ekspor (.txt/.md) | modul Catatan, lanjutan --}}

@section('title', 'Catatan Kerja')

@php
    // Semua data di halaman ini milik user yang sedang login (daftar & pilihan
    // sudah disaring `ownedBy` di controller), jadi tidak ada catatan orang
    // lain yang sampai ke halaman.
    //
    // AGENT: asisten | 2026-10-01 | buat berkas baru | modul Catatan Kerja, fase 1 backend
    $selectedId = $selected?->getKey();
@endphp

@section('content')
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-ink-900">Catatan Kerja</h1>
            <p class="text-sm text-ink-500">
                Catatan pribadi Anda, tersimpan di akun &mdash; bukan di PC. Pindah komputer, catatan tetap ada.
            </p>
        </div>

        {{-- Form biasa (bukan fetch): tetap berfungsi walau JavaScript mati. --}}
        <div class="flex shrink-0 items-center gap-2">
            {{-- Ekspor semua catatan aktif. Dua tautan (bukan menu dropdown)
                 supaya tetap dapat dipakai tanpa JavaScript. --}}
            <a href="{{ route('notes.export.all', ['format' => 'txt']) }}" class="btn-secondary"
               title="Unduh semua catatan sebagai teks">
                Ekspor .txt
            </a>
            <a href="{{ route('notes.export.all', ['format' => 'md']) }}" class="btn-secondary"
               title="Unduh semua catatan sebagai Markdown">
                Ekspor .md
            </a>

            <form method="POST" action="{{ route('notes.store') }}">
                @csrf
                <button type="submit" class="btn-primary">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                    </svg>
                    Catatan Baru
                </button>
            </form>
        </div>
    </div>

    <div
        class="grid gap-4 lg:grid-cols-[20rem_minmax(0,1fr)]"
        x-data="noteEditor({
            delay: {{ (int) $autosaveDelay }},
            limit: {{ (int) $bodyLimit }},
            id: {{ $selectedId ?: 'null' }},
            version: {{ $selected?->lock_version ?? 'null' }}
        })"
    >
        {{-- Kolom kiri: daftar catatan + tong sampah --}}
        <aside class="card flex min-h-0 flex-col">
            <form method="GET" action="{{ route('notes.index') }}" class="border-b border-ink-100 p-3">
                <label for="q" class="sr-only">Cari catatan</label>
                <input
                    type="search"
                    id="q"
                    name="q"
                    value="{{ $search }}"
                    class="form-input"
                    placeholder="Cari catatan..."
                >
                {{-- Label aktif ikut terbawa saat mencari, supaya saringan
                     tidak hilang tanpa disadari. --}}
                @if ($tagId)
                    <input type="hidden" name="tag" value="{{ $tagId }}">
                @endif
            </form>

            {{-- Panel saring label (PRD Catatan §14). Tautan biasa agar tetap
                 berfungsi tanpa JavaScript; "Semua" mengosongkan saringan. --}}
            @if ($tags->isNotEmpty())
                <div class="flex flex-wrap items-center gap-1.5 border-b border-ink-100 px-3 py-2">
                    <a href="{{ route('notes.index', array_filter(['q' => $search])) }}"
                       class="badge-{{ $tagId ? 'gray' : 'brand' }}">Semua</a>

                    @foreach ($tags as $tag)
                        <a href="{{ route('notes.index', array_filter(['q' => $search, 'tag' => $tag->getKey()])) }}"
                           class="badge-{{ $tagId === $tag->getKey() ? 'brand' : 'gray' }}"
                           title="{{ $tag->notes_count }} catatan">
                            {{ $tag->name }}
                            <span class="text-[10px] opacity-70">{{ $tag->notes_count }}</span>
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($notes->isEmpty())
                <p class="p-4 text-sm text-ink-500">
                    {{ filled($search) ? 'Tidak ada catatan yang cocok dengan pencarian Anda.' : 'Belum ada catatan. Klik "Catatan Baru" untuk mulai.' }}
                </p>
            @else
                <ul class="max-h-[32rem] min-h-0 overflow-y-auto">
                    @foreach ($notes as $note)
                        @php $isActive = $selectedId === $note->getKey(); @endphp
                        <li class="border-b border-ink-50 last:border-b-0">
                            <a
                                href="{{ route('notes.index', array_filter(['note' => $note->getKey(), 'q' => $search])) }}"
                                class="block px-3 py-2.5 transition-colors {{ $isActive ? 'bg-brand-50/70' : 'hover:bg-ink-50' }}"
                            >
                                <span class="flex items-center gap-1.5">
                                    @if ($note->pinned)
                                        <svg class="h-3.5 w-3.5 shrink-0 text-brand-600" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M14 3l7 7-4 1-3 3 1 3-1 1-4-4-4 4-1-1 4-4-4-4 1-1 3 1 3-3z" />
                                        </svg>
                                    @endif
                                    <span class="truncate text-sm font-medium text-ink-900">{{ $note->display_title }}</span>
                                </span>

                                <span class="mt-0.5 flex items-center gap-2 text-xs text-ink-500">
                                    <span>{{ $note->last_edited_at?->format('d/m/Y H:i') ?? 'Baru dibuat' }}</span>
                                    @if ($note->isUntouched())
                                        <span class="badge-gray">belum diisi</span>
                                    @endif
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>

                @if ($notes->hasPages())
                    <div class="border-t border-ink-100 p-3">{{ $notes->links() }}</div>
                @endif
            @endif

            @if ($trashed->isNotEmpty())
                <details class="border-t border-ink-100 p-3 text-sm">
                    <summary class="cursor-pointer text-ink-600">
                        Tong sampah ({{ $trashed->count() }})
                    </summary>

                    <ul class="mt-2 space-y-2">
                        @foreach ($trashed as $item)
                            <li class="flex items-center justify-between gap-2">
                                <span class="truncate text-ink-600">{{ $item->display_title }}</span>

                                {{-- Catatan yang dihapus tidak pernah hilang permanen:
                                     pemulihan selalu tersedia untuk pemiliknya. --}}
                                <form method="POST" action="{{ route('notes.restore', $item) }}">
                                    @csrf
                                    <button type="submit" class="btn-ghost btn-sm">Pulihkan</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </aside>

        {{-- Kolom kanan: editor --}}
        <main class="card flex min-h-0 flex-col">
            @if (! $selected)
                <x-empty-state
                    title="Belum ada catatan dipilih"
                    description="Buat catatan baru untuk mulai menulis. Catatan tersimpan di akun Anda, jadi tetap ada saat berpindah PC."
                />
            @else
                {{-- BANNER konflik antar-PC/antar-tab: muncul hanya bila server
                     menolak simpan karena versi catatan sudah berubah (409). --}}
                <div x-show="conflict" x-cloak class="border-b border-amber-200 bg-amber-50 px-5 py-3 text-sm text-amber-800" role="alert">
                    <p class="font-semibold">Catatan ini sudah diperbarui di tempat lain.</p>
                    <p class="mt-0.5">
                        Agar tulisan tidak saling bertimpa, penyimpanan otomatis dihentikan.
                        Muat ulang halaman untuk melihat versi terbaru, lalu salin tulisan Anda.
                    </p>
                    <a href="{{ route('notes.index', ['note' => $selectedId]) }}" class="link mt-1 inline-block">Muat ulang halaman</a>
                </div>

                {{-- BANNER draf lokal (koneksi putus / tab ditutup paksa). --}}
                <div x-show="draftFound" x-cloak class="border-b border-brand-200 bg-brand-50 px-5 py-3 text-sm text-brand-800" role="status">
                    <p class="font-semibold">Ada draf yang belum tersimpan di PC ini.</p>
                    <p class="mt-0.5">Draf ini tersimpan sementara di peramban ketika koneksi terputus.</p>
                    <div class="mt-2 flex gap-2">
                        <button type="button" class="btn-secondary btn-sm" @click="restoreDraft()">Pakai draf</button>
                        <button type="button" class="btn-ghost btn-sm" @click="discardDraft()">Buang</button>
                    </div>
                </div>

                <div class="card-header flex-wrap">
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-ink-500">
                            Terakhir disunting:
                            <span class="font-medium text-ink-700">{{ $selected->last_edited_at?->format('d/m/Y H:i') ?? 'belum pernah diisi' }}</span>
                        </span>
                        @if ($selected->pinned)
                            <span class="badge-brand">disematkan</span>
                        @endif
                    </div>

                    <div class="flex items-center gap-2">
                        {{-- Ekspor catatan ini saja (PRD Catatan §14). --}}
                        <a href="{{ route('notes.export', ['note' => $selected, 'format' => 'txt']) }}"
                           class="btn-ghost btn-sm" title="Unduh catatan ini sebagai teks">Ekspor .txt</a>
                        <a href="{{ route('notes.export', ['note' => $selected, 'format' => 'md']) }}"
                           class="btn-ghost btn-sm" title="Unduh catatan ini sebagai Markdown">Ekspor .md</a>

                        <form method="POST" action="{{ route('notes.pin', $selected) }}">
                            @csrf
                            <button type="submit" class="btn-ghost btn-sm">
                                {{ $selected->pinned ? 'Lepas sematan' : 'Sematkan' }}
                            </button>
                        </form>

                        <form method="POST" action="{{ route('notes.destroy', $selected) }}"
                              onsubmit="return confirm('Pindahkan catatan ini ke tong sampah? Masih bisa dipulihkan.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-ghost btn-sm text-red-600 hover:bg-red-50">Hapus</button>
                        </form>
                    </div>
                </div>

                {{-- Panel label (PRD Catatan §14). Form TERPISAH dari form isi
                     catatan: HTML melarang form bersarang, dan menyatukan
                     keduanya akan membuat autosave ikut mengirim label. --}}
                <form method="POST" action="{{ route('notes.tags.sync', $selected) }}"
                      class="border-b border-ink-100 px-5 py-3 sm:px-6">
                    @csrf
                    @method('PUT')

                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-medium text-ink-500">Label:</span>

                        @forelse ($tags as $tag)
                            <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-ink-200 px-2.5 py-1 text-xs
                                          has-[:checked]:border-brand-300 has-[:checked]:bg-brand-50 has-[:checked]:text-brand-700">
                                <input type="checkbox" name="tags[]" value="{{ $tag->getKey() }}"
                                       class="h-3.5 w-3.5 rounded border-ink-300 text-brand-600 focus:ring-brand-500"
                                       @checked($selected->tags->contains($tag->getKey()))>
                                {{ $tag->name }}
                            </label>
                        @empty
                            <span class="text-xs text-ink-400">Belum ada label. Tambahkan di samping.</span>
                        @endforelse

                        <button type="submit" class="btn-ghost btn-sm">Simpan label</button>
                    </div>
                </form>

                {{-- Form label baru + hapus label, diletakkan berdampingan agar
                     jelas keduanya mengelola DAFTAR label, bukan catatan ini. --}}
                <div class="flex flex-wrap items-center gap-2 border-b border-ink-100 px-5 py-2 sm:px-6">
                    <form method="POST" action="{{ route('notes.tags.store') }}" class="flex items-center gap-2">
                        @csrf
                        <label for="new-tag" class="sr-only">Nama label baru</label>
                        <input type="text" id="new-tag" name="name" class="form-input h-8 text-xs"
                               maxlength="{{ (int) config('notes.tag_max_length') }}"
                               placeholder="Label baru...">
                        <button type="submit" class="btn-ghost btn-sm">Tambah</button>
                    </form>

                    @foreach ($tags as $tag)
                        <form method="POST" action="{{ route('notes.tags.destroy', $tag) }}"
                              onsubmit="return confirm('Hapus label {{ $tag->name }}? Catatan yang memakainya tidak terhapus.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-ghost btn-sm text-ink-400 hover:bg-red-50 hover:text-red-600"
                                    title="Hapus label {{ $tag->name }}">
                                {{ $tag->name }} &times;
                            </button>
                        </form>
                    @endforeach
                </div>

                <form
                    data-note-form
                    method="POST"
                    action="{{ route('notes.update', $selected) }}"
                    class="flex min-h-0 flex-1 flex-col"
                    @submit.prevent="save(true)"
                    @input="onInput()"
                >
                    @csrf
                    @method('PUT')

                    <div class="p-5 sm:p-6">
                        <label for="note-title" class="sr-only">Judul catatan</label>
                        <input
                            type="text"
                            id="note-title"
                            name="title"
                            data-note-title
                            value="{{ $selected->title }}"
                            maxlength="{{ (int) config('notes.title_max_length') }}"
                            class="form-input text-base font-semibold"
                            placeholder="Judul (boleh kosong)"
                        >

                        <label for="note-body" class="sr-only">Isi catatan</label>
                        <textarea
                            id="note-body"
                            name="body"
                            data-note-body
                            rows="16"
                            maxlength="{{ (int) $bodyLimit }}"
                            class="form-input mt-3 font-mono leading-relaxed"
                            placeholder="Tulis catatan pekerjaan Anda di sini..."
                        >{{ $selected->body }}</textarea>
                    </div>

                    <div class="mt-auto flex flex-wrap items-center gap-3 border-t border-ink-100 px-5 py-3 sm:px-6">
                        <button type="submit" class="btn-primary btn-sm">
                            <span x-show="!saving">Simpan</span>
                            <span x-show="saving" x-cloak>Menyimpan...</span>
                        </button>

                        {{-- Status simpan. Tanpa JavaScript baris ini hanya menjadi
                             penjelasan; form tetap terkirim biasa. --}}
                        <span class="text-xs text-ink-500" aria-live="polite">
                            <span x-show="saving" x-cloak>Menyimpan ke server...</span>
                            <span x-show="!saving && dirty" x-cloak class="text-amber-700">Ada perubahan belum tersimpan</span>
                            <span x-show="!saving && !dirty && savedAt" x-cloak class="text-emerald-700">
                                Tersimpan <span x-text="savedAt"></span>
                            </span>
                            <span x-show="!saving && !dirty && !savedAt" x-cloak>Perubahan tersimpan otomatis saat Anda mengetik.</span>
                            <span x-show="error" x-cloak class="text-red-700" x-text="error"></span>
                        </span>
                    </div>
                </form>
            @endif
        </main>
    </div>
@endsection


@push('scripts')
    <script>
        // Autosave Catatan Kerja (PRD Catatan).
        //
        // Tiga lapis penjagaan agar catatan tidak hilang:
        // 1. Autosave ke server setelah jeda mengetik - inti solusinya, karena
        //    catatan mengikuti AKUN, bukan perangkat.
        // 2. Cadangan draf di localStorage - menutup celah saat koneksi putus
        //    atau tab ditutup sebelum autosave sempat berjalan.
        // 3. Versi (lock_version) - dua PC yang membuka catatan sama tidak
        //    saling menimpa; server menolak dengan 409.
        //
        // Tanpa JavaScript, form tetap terkirim biasa (POST + _method=PUT),
        // jadi menyimpan tidak pernah sepenuhnya bergantung pada skrip ini.
        document.addEventListener('alpine:init', () => {
            Alpine.data('noteEditor', (config = {}) => ({
                delay: Number(config.delay || 1500),
                limit: Number(config.limit || 50000),
                noteId: config.id ?? null,
                version: config.version ?? null,

                dirty: false,
                saving: false,
                savedAt: '',
                error: '',
                conflict: false,
                draftFound: false,

                init() {
                    this.form = this.$root.querySelector('[data-note-form]');

                    if (this.form) {
                        this.titleInput = this.form.querySelector('[data-note-title]');
                        this.bodyInput = this.form.querySelector('[data-note-body]');
                    }

                    this.draftKey = this.noteId ? `fsb-note-draft-${this.noteId}` : '';
                    this.checkLocalDraft();

                    // Peringatan sebelum meninggalkan halaman bila masih ada
                    // ketikan yang belum terkirim ke server.
                    window.addEventListener('beforeunload', (event) => {
                        if (!this.dirty && !this.saving) {
                            return;
                        }

                        event.preventDefault();
                        event.returnValue = '';
                    });
                },

                onInput() {
                    this.dirty = true;
                    this.error = '';
                    this.backupDraft();

                    clearTimeout(this.timer);
                    this.timer = setTimeout(() => this.save(), this.delay);
                },

                /** Cadangan draf di peramban (lapis 2). */
                backupDraft() {
                    if (!this.draftKey || !this.bodyInput) {
                        return;
                    }

                    try {
                        localStorage.setItem(this.draftKey, JSON.stringify({
                            title: this.titleInput ? this.titleInput.value : '',
                            body: this.bodyInput.value,
                            at: Date.now(),
                        }));
                    } catch (exception) {
                        // Penyimpanan penuh atau mode privat: abaikan, karena
                        // autosave ke server tetap berjalan.
                    }
                },

                checkLocalDraft() {
                    const draft = this.readLocalDraft();

                    if (!draft) {
                        return;
                    }

                    // Draf yang isinya sama dengan server sudah tidak berguna.
                    if (draft.body && this.bodyInput && draft.body !== this.bodyInput.value) {
                        this.draftFound = true;
                    } else {
                        this.discardDraft();
                    }
                },

                readLocalDraft() {
                    if (!this.draftKey) {
                        return null;
                    }

                    try {
                        const raw = localStorage.getItem(this.draftKey);

                        return raw ? JSON.parse(raw) : null;
                    } catch (exception) {
                        return null;
                    }
                },

                restoreDraft() {
                    const draft = this.readLocalDraft();

                    if (!draft) {
                        this.draftFound = false;

                        return;
                    }

                    if (this.titleInput) {
                        this.titleInput.value = draft.title || '';
                    }

                    if (this.bodyInput) {
                        this.bodyInput.value = draft.body || '';
                    }

                    this.draftFound = false;
                    this.onInput();
                },

                discardDraft() {
                    this.draftFound = false;

                    try {
                        if (this.draftKey) {
                            localStorage.removeItem(this.draftKey);
                        }
                    } catch (exception) {
                        // Diabaikan: draf hanyalah jaring pengaman tambahan.
                    }
                },


                async save(manual = false) {
                    if (!this.form || this.saving || this.conflict) {
                        return;
                    }

                    clearTimeout(this.timer);
                    this.saving = true;
                    this.error = '';

                    try {
                        const response = await fetch(this.form.getAttribute('action'), {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: this.payload(),
                        });

                        await this.handle(response);
                    } catch (exception) {
                        // Server tidak terjangkau: draf sudah ada di localStorage,
                        // jadi ketikan belum hilang dan akan dicoba lagi.
                        this.error = 'Gagal menyimpan (koneksi terputus). Tulisan Anda masih tersimpan di peramban.';
                    } finally {
                        this.saving = false;
                    }
                },

                payload() {
                    const data = new FormData();

                    data.append('_method', 'PUT');
                    data.append('_token', document.querySelector('meta[name="csrf-token"]').content);
                    data.append('title', this.titleInput ? this.titleInput.value : '');
                    data.append('body', this.bodyInput ? this.bodyInput.value : '');

                    // Versi yang dipegang tab ini. Bila catatan sudah berubah di
                    // PC lain, server menolak dan tidak menimpa.
                    if (this.version !== null) {
                        data.append('lock_version', String(this.version));
                    }

                    return data;
                },

                async handle(response) {
                    if (response.status === 409) {
                        this.conflict = true;
                        this.dirty = false;

                        return;
                    }

                    if (response.status === 422) {
                        const body = await response.json();
                        this.error = body.errors
                            ? Object.values(body.errors).flat()[0]
                            : 'Catatan tidak dapat disimpan.';

                        return;
                    }

                    if (!response.ok) {
                        this.error = 'Gagal menyimpan. Muat ulang halaman lalu coba lagi.';

                        return;
                    }

                    const body = await response.json();

                    if (body.note) {
                        this.version = body.note.lock_version;
                    }

                    this.savedAt = body.saved_at || '';
                    this.dirty = false;
                    this.error = '';
                    this.discardDraft();
                },
            }));
        });
    </script>
@endpush

