@extends('layouts.app')

{{-- AGENT: asisten | 2026-10-06 | pencarian, bilah sematan, panel utas, composer balas/sunting | modul Chat §15 --}}
{{-- AGENT: asisten | 2026-10-10 | layout 2 kolom + #chat-main yang ditukar + teardown/swapToken | pindah chat tanpa reload --}}

@section('title', $conversation->titleFor(auth()->user()))

@php($me = auth()->user())
@php($isChannel = $conversation->isChannel())

@section('content')
    {{-- Dua kolom: panel daftar percakapan (kiri) dan area percakapan
         (kanan). Di layar sempit panel disembunyikan; halaman daftar penuh
         tetap tersedia lewat tautan "Kembali ke daftar chat". --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[20rem_minmax(0,1fr)]">
        <div class="hidden lg:block">
            @include('chat._conversation_panel', [
                'conversations' => $conversations,
                'unread' => $unread,
                'activeId' => $conversation->id,
            ])
        </div>

        {{-- Region yang ditukar saat berpindah percakapan tanpa memuat ulang.
             Semua yang khas percakapan (judul, riwayat, composer, panel utas,
             modal anggota) berada di dalam sini. Panel samping sengaja di
             LUAR agar posisi gulirnya bertahan saat berpindah-pindah. --}}
        <div id="chat-main" data-chat-main>
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('chat.index') }}" class="btn-icon shrink-0" aria-label="Kembali ke daftar chat">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                <h1 class="truncate text-xl font-semibold text-ink-900">{{ $conversation->titleFor($me) }}</h1>

                @if ($isChannel)
                    <x-badge :variant="$conversation->is_private ? 'amber' : 'gray'">
                        {{ $conversation->is_private ? 'Privat' : 'Publik' }}
                    </x-badge>
                    <span class="text-sm text-ink-500">{{ $conversation->members->count() }} anggota</span>
                @else
                    <x-badge variant="gray">Percakapan pribadi</x-badge>
                @endif
            </div>

            @if ($isChannel && $conversation->description)
                <p class="mt-1 text-sm text-ink-500">{{ $conversation->description }}</p>
            @endif
        </div>

        @if ($canManageMembers)
            <button type="button" class="btn-secondary shrink-0" @click="$dispatch('open-modal', 'channel-members')">
                Kelola Anggota
            </button>
        @endif
    </div>

    {{-- Pencarian pesan (PRD §15). Hasil ditampilkan di dalam area pesan
         supaya tampilannya identik dengan riwayat (partial yang sama). --}}
    <div class="mb-3 flex items-center gap-2">
        <div class="relative flex-1">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400"
                 fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 1 1-12 0 6 6 0 0 1 12 0Z" />
            </svg>
            <input type="search" id="chat-search" class="form-input pl-9" autocomplete="off"
                   placeholder="Cari pesan di percakapan ini..."
                   data-search-url="{{ route('chat.messages.search', $conversation) }}">
        </div>
        <button type="button" class="btn-secondary hidden shrink-0" data-chat-search-clear>Batal</button>
    </div>

    {{-- Bilah sematan (PRD §15). Diringkas menjadi satu baris; klik
         menggulir ke pesan aslinya di riwayat. Wadahnya SELALU dirender
         (kosong + hidden) supaya JavaScript dapat memperbaruinya saat
         sematan ditambah/dilepas tanpa memuat ulang halaman. --}}
    <div class="mb-3 rounded-xl border border-brand-200 bg-brand-50/60 px-4 py-2.5 {{ $pinned->isEmpty() ? 'hidden' : '' }}"
         data-chat-pinned-bar>
        <div class="flex items-center gap-2 text-xs font-semibold text-brand-700">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 3l6 6-3 1-4 4-1 4-3-3-4 4-1-1 4-4-3-3 4-1 4-4 1-3Z" />
            </svg>
            <span>Pesan disematkan (<span data-chat-pinned-count>{{ $pinned->count() }}</span>)</span>
        </div>
        <ul class="mt-1.5 space-y-1" data-chat-pinned-list>
            @foreach ($pinned as $pin)
                <li>
                    <button type="button" class="chat-pin-item" data-chat-jump="{{ $pin->id }}"
                            data-pin-author="{{ $pin->user?->name ?? 'Pengguna dihapus' }}"
                            data-pin-body="{{ \Illuminate\Support\Str::limit($pin->body, 140) }}">
                        <span class="chat-pin-author">{{ $pin->user?->name ?? 'Pengguna dihapus' }}</span>
                        <span class="chat-pin-body">{{ \Illuminate\Support\Str::limit($pin->body, 140) }}</span>
                    </button>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="card flex flex-col overflow-hidden">
        <div
            id="chat-messages"
            class="chat-scroll relative flex max-h-[65vh] min-h-[24rem] flex-col overflow-y-auto"
            role="log"
            aria-live="polite"
            data-poll-url="{{ route('chat.messages.poll', $conversation) }}"
            data-poll-interval="{{ $pollInterval }}"
            data-last-id="{{ $messages->last()?->id ?? 0 }}"
            data-current-user="{{ $me->id }}"
            data-thread-url-template="{{ route('chat.messages.thread', [$conversation, '__ID__']) }}"
        >
            @forelse ($messages as $message)
                @include('chat._message', [
                    'message' => $message,
                    'conversation' => $conversation,
                    'previousMessage' => $loop->first ? null : $messages[$loop->index - 1],
                ])
            @empty
                <div class="chat-empty" data-chat-empty>
                    <div class="chat-empty-icon">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h8M8 8h5M21 12a8.5 8.5 0 0 1-8.5 8.5c-1.4 0-2.7-.3-3.9-.9L3 21l1.4-5.6A8.5 8.5 0 1 1 21 12Z" />
                        </svg>
                    </div>
                    <p class="chat-empty-title">Belum ada pesan</p>
                    <p class="chat-empty-text">Mulai percakapan ini dengan mengirim pesan pertama.</p>
                </div>
            @endforelse

            {{-- Tombol melompat ke bawah bila pesan baru masuk saat pengguna
                 sedang membaca pesan lama (A2). --}}
            <button type="button" class="chat-new-button hidden" data-chat-new-messages>
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                </svg>
                <span data-chat-new-count>1</span> pesan baru
            </button>
        </div>

        {{-- Form kirim pesan. Batas panjang & tipe lampiran berasal dari
             config (PRD §8), bukan ditulis ulang di sini. --}}
        @if ($conversation->isDirect() && ! $conversation->otherParticipant($me)?->isActive())
            <div class="border-t border-ink-100 bg-amber-50/60 px-5 py-4">
                <p class="text-sm text-amber-800">
                    Akun rekan bicara Anda sedang nonaktif, sehingga pesan baru tidak dapat dikirim.
                    Riwayat percakapan tetap dapat dibaca.
                </p>
            </div>
        @else
            <div class="border-t border-ink-100 bg-ink-50/40 px-4 py-4 sm:px-5">
                {{-- Keadaan "sedang membalas" / "sedang menyunting" (PRD §15).
                     Keduanya memakai composer yang sama supaya pengguna tidak
                     berpindah konteks; hanya label dan aksi kirim yang berubah. --}}
                <div class="hidden mb-3 items-center justify-between gap-3 rounded-xl border border-brand-200 bg-brand-50/80 px-3.5 py-2"
                     data-chat-composer-state>
                    <div class="flex min-w-0 items-center gap-2">
                        <svg class="h-4 w-4 shrink-0 text-brand-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a5 5 0 0 1 5 5v3m0 0l-3-3m3 3l3-3" />
                        </svg>
                        <p class="min-w-0 truncate text-xs font-medium text-brand-900" data-chat-composer-label></p>
                    </div>
                    <button type="button" class="shrink-0 rounded-md px-2 py-0.5 text-xs font-semibold text-brand-700 hover:bg-brand-100/60"
                            data-chat-composer-cancel aria-label="Batal balas atau sunting">Batal</button>
                </div>

                <form method="POST" action="{{ route('chat.messages.store', $conversation) }}"
                      enctype="multipart/form-data" class="space-y-2" data-chat-form
                      data-store-url="{{ route('chat.messages.store', $conversation) }}">
                    @csrf
                    <input type="hidden" name="parent_id" value="" data-chat-parent-id>

                    {{-- Container Lampiran / File Chips Pratinjau --}}
                    <div class="hidden flex-wrap gap-2 pb-1" data-chat-file-preview-list></div>

                    {{-- Hidden file input yang dipicu tombol paperclip --}}
                    <input id="attachments" name="attachments[]" type="file" multiple class="sr-only" data-chat-file-input
                           accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip">

                    {{-- Unified Modern Composer Dock --}}
                    <div class="chat-composer-dock flex items-end gap-2 rounded-2xl border border-ink-200 bg-white p-1.5 shadow-sm focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-500/20">
                        {{-- Tombol Paperclip Lampiran --}}
                        <button type="button" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-ink-500 transition-colors hover:bg-ink-100 hover:text-ink-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                                data-chat-attachment-btn title="Tambah lampiran (maks. {{ intdiv($attachmentMaxKb, 1024) }} MB per berkas)"
                                aria-label="Tambah lampiran berkas">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                            </svg>
                        </button>

                        {{-- Textarea Auto-grow --}}
                        <div class="relative flex-1">
                            <label for="body" class="sr-only">Pesan</label>
                            <textarea id="body" name="body" rows="1" maxlength="{{ $messageMaxLength }}"
                                      class="block w-full resize-none border-0 bg-transparent py-1.5 px-1 text-sm text-ink-900 placeholder-ink-400 focus:ring-0 focus:outline-none max-h-36 overflow-y-auto leading-relaxed"
                                      placeholder="Tulis pesan... (Enter untuk kirim, Shift+Enter untuk baris baru)">{{ old('body') }}</textarea>
                        </div>

                        {{-- Tombol Kirim Terintegrasi --}}
                        <button type="submit" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-600 text-white shadow-sm transition-all hover:bg-brand-700 active:scale-95 disabled:opacity-50 disabled:pointer-events-none focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
                                data-chat-submit title="Kirim pesan" aria-label="Kirim pesan">
                            <svg class="h-4 w-4 transform rotate-90" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                            </svg>
                            <span class="sr-only">Kirim</span>
                        </button>
                    </div>

                    <div class="flex items-center justify-between px-1 text-[11px] text-ink-400">
                        <p>
                            Maks. {{ intdiv($attachmentMaxKb, 1024) }} MB per berkas. Disimpan privat untuk anggota percakapan.
                        </p>
                        <p class="hidden sm:inline">
                            Enter kirim &bull; Shift+Enter baris baru
                        </p>
                    </div>

                    {{-- Error feedback --}}
                    <div data-chat-errors class="hidden space-y-1">
                        <p class="text-xs text-red-600" data-chat-error-text></p>
                    </div>
                    @error('body')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    @error('attachments')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    @error('attachments.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </form>
            </div>
        @endif
    </div>

    {{-- Panel utas (PRD §15). Isinya dimuat lewat endpoint utas agar riwayat
         utama tidak ikut membesar. Panel disembunyikan sampai ada balasan. --}}
    <aside id="chat-thread" class="card mt-3 hidden flex-col overflow-hidden">
        <div class="flex items-center justify-between gap-3 border-b border-ink-100 px-4 py-3">
            <h2 class="text-sm font-semibold text-ink-800">Utas</h2>
            <button type="button" class="btn-icon" data-chat-thread-close aria-label="Tutup utas">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" />
                </svg>
            </button>
        </div>
        <div class="chat-scroll max-h-[50vh] overflow-y-auto py-3" data-chat-thread-body></div>
        <p class="hidden border-t border-ink-100 px-4 py-3 text-sm text-ink-500" data-chat-thread-status></p>
    </aside>

    @if ($canManageMembers)
        @include('chat._members_modal')
    @endif
        </div>{{-- /#chat-main --}}
    </div>{{-- /grid --}}
@endsection

@push('scripts')
    <script>
        // Seluruh skrip halaman percakapan dibungkus `initChat()` yang
        // mengembalikan fungsi pembersih. Ini WAJIB karena area percakapan
        // dapat ditukar tanpa memuat ulang halaman saat pengguna berpindah
        // chat: tanpa pembersihan, `setInterval` dan listener tingkat
        // `document` akan menumpuk dan terus menunjuk elemen yang sudah
        // dibuang (kebocoran memori + polling berganda).
        (() => {
            let teardown = null;

            const initChat = () => {
            // Polling hanya saat tab AKTIF (PRD §9): saat pengguna berpindah tab,
            // penjadwalan dihentikan agar tidak membebani server. Karena itu
            // jadwalnya dibuat ulang mengikuti visibilitychange, bukan memakai
            // satu setInterval yang jalan terus.
            const box = document.getElementById('chat-messages');
            const form = document.querySelector('[data-chat-form]');
            const newButton = box?.querySelector('[data-chat-new-messages]');
            const newCount = box?.querySelector('[data-chat-new-count]');

            if (!box) {
                return null;
            }

            // Listener tingkat `document`/`window` TIDAK ikut terbuang saat isi
            // area percakapan ditukar (berbeda dengan listener pada elemen di
            // dalamnya, yang ikut hilang bersama elemennya), jadi hanya
            // listener inilah yang perlu dilepas manual.
            const globalListeners = [];
            const onDocument = (type, handler) => {
                document.addEventListener(type, handler);
                globalListeners.push(() => document.removeEventListener(type, handler));
            };
            const onWindow = (type, handler) => {
                window.addEventListener(type, handler);
                globalListeners.push(() => window.removeEventListener(type, handler));
            };

            const pollUrl = box.dataset.pollUrl;
            const interval = Number(box.dataset.pollInterval || 5000);
            const currentUserId = box.dataset.currentUser;
            let lastId = Number(box.dataset.lastId || 0);
            let unreadCount = 0;

            // A3: draf "Mengirim..." yang masih menunggu pesan servernya.
            // Isi dipakai untuk memasangkan draf dengan pesan yang datang
            // dari polling.
            let pendingDrafts = [];

            const atBottom = () => box.scrollHeight - box.scrollTop - box.clientHeight < 80;
            const scrollToBottom = () => { box.scrollTop = box.scrollHeight; };

            const updateNewButton = () => {
                if (!newButton) return;
                const show = unreadCount > 0 && !atBottom();
                newButton.classList.toggle('hidden', !show);
                if (newCount) newCount.textContent = String(unreadCount);
            };

            const clearEmptyState = () => {
                const empty = box.querySelector('[data-chat-empty]');
                if (empty) empty.remove();
            };

            const escapeHtml = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

            // Perhatian: struktur template ini harus sama persis dengan
            // resources/views/chat/_message.blade.php. Partial Blade tetap
            // satu-satunya sumber kebenaran untuk pesan server; template ini
            // hanya dipakai untuk draf sementara sebelum poll tiba.
            const renderDraft = (body, previousDate) => {
                const now = new Date();
                const isoDate = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
                const longDate = now.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });

                // Hari sama dengan pesan terakhir = tidak perlu pemisah hari.
                // Tidak ada pemeriksaan "Kemarin" di sini: penanggalan server
                // (Asia/Jakarta) lebih andal daripada timezone browser, dan
                // draf diganti cepat oleh pesan server.
                const separator = previousDate === isoDate
                    ? ''
                    : `<div class="chat-day-separator" data-date="${isoDate}"><span>${longDate}</span></div>`;

                return `${separator}`
                    + `<div class="chat-message chat-message--mine chat-message--draft" data-message-id="" data-sender-id="me" data-date="${isoDate}" data-pending="1">`
                    + `<div class="chat-bubble-col chat-bubble-col--mine">`
                    + `<div class="chat-bubble chat-bubble-mine">`
                    + `<p class="chat-bubble-body" data-draft-body>${escapeHtml(body)}</p>`
                    + `</div>`
                    + `<p class="chat-draft-status">Mengirim...</p>`
                    + `</div>`
                    + `</div>`;
            };

            const insertDraft = (body) => {
                clearEmptyState();

                const nodes = box.querySelectorAll('.chat-message');
                const previousDate = nodes.length ? nodes[nodes.length - 1].dataset.date : null;

                box.insertAdjacentHTML('beforeend', renderDraft(body, previousDate));

                const draft = box.querySelector('.chat-message:last-of-type');
                pendingDrafts.push({ body, node: draft });

                scrollToBottom();
            };

            const removeDraft = (body) => {
                const index = pendingDrafts.findIndex((draft) => draft.body === body);
                if (index === -1) return;
                pendingDrafts[index].node?.remove();
                pendingDrafts.splice(index, 1);
            };

            /* --------------------------------------------------------- */
            /* Aksi pesan: balas, sunting, hapus, semat (PRD §15)        */
            /* --------------------------------------------------------- */

            const composerState = document.querySelector('[data-chat-composer-state]');
            const composerLabel = document.querySelector('[data-chat-composer-label]');
            const parentInput = form?.querySelector('[data-chat-parent-id]');
            const textarea = form?.querySelector('textarea[name="body"]');
            const submitButton = form?.querySelector('[data-chat-submit]');
            const fileInput = form?.querySelector('[data-chat-file-input]');
            const attachmentBtn = form?.querySelector('[data-chat-attachment-btn]');
            const filePreviewList = form?.querySelector('[data-chat-file-preview-list]');
            const errorContainer = form?.querySelector('[data-chat-errors]');
            const errorText = form?.querySelector('[data-chat-error-text]');

            const autoResizeTextarea = () => {
                if (!textarea) return;
                textarea.style.height = 'auto';
                textarea.style.height = Math.min(textarea.scrollHeight, 144) + 'px';
            };

            textarea?.addEventListener('input', autoResizeTextarea);

            attachmentBtn?.addEventListener('click', () => {
                fileInput?.click();
            });

            let dt = new DataTransfer();

            const renderFilePreviews = () => {
                if (!filePreviewList || !fileInput) return;
                filePreviewList.innerHTML = '';
                const files = dt.files;
                if (files.length === 0) {
                    filePreviewList.classList.add('hidden');
                    return;
                }
                filePreviewList.classList.remove('hidden');

                Array.from(files).forEach((file, index) => {
                    const chip = document.createElement('div');
                    chip.className = 'inline-flex items-center gap-1.5 rounded-lg border border-ink-200 bg-white px-2.5 py-1 text-xs text-ink-700 shadow-xs';
                    const icon = '<svg class="h-3.5 w-3.5 text-brand-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>';
                    const name = document.createElement('span');
                    name.className = 'max-w-[140px] truncate font-medium';
                    name.textContent = file.name;
                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.className = 'ml-1 text-ink-400 hover:text-red-600 focus:outline-none';
                    removeBtn.innerHTML = '<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>';
                    removeBtn.setAttribute('aria-label', 'Hapus berkas ' + file.name);

                    removeBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        const nextDt = new DataTransfer();
                        Array.from(dt.files).forEach((f, i) => {
                            if (i !== index) nextDt.items.add(f);
                        });
                        dt = nextDt;
                        fileInput.files = dt.files;
                        renderFilePreviews();
                    });

                    chip.innerHTML = icon;
                    chip.appendChild(name);
                    chip.appendChild(removeBtn);
                    filePreviewList.appendChild(chip);
                });
            };

            fileInput?.addEventListener('change', () => {
                if (fileInput.files) {
                    Array.from(fileInput.files).forEach(file => {
                        if (dt.items.length < 5) {
                            dt.items.add(file);
                        }
                    });
                    fileInput.files = dt.files;
                    renderFilePreviews();
                }
            });

            const clearFiles = () => {
                dt = new DataTransfer();
                if (fileInput) fileInput.files = dt.files;
                renderFilePreviews();
            };

            // Galat validasi server (422) ditampilkan DI DALAM halaman, bukan
            // dengan memuat ulang. Ini penting: reload akan menghapus berkas
            // yang sudah dipilih pengguna, sehingga kesalahan sekecil apa pun
            // memaksa mereka memilih lampiran dari awal.
            const showErrors = (messages) => {
                if (!errorContainer || !errorText) return;
                const list = Array.isArray(messages) ? messages : [messages];
                errorText.textContent = list.filter(Boolean).join(' ');
                errorContainer.classList.remove('hidden');
            };

            const clearErrors = () => {
                if (!errorContainer) return;
                errorContainer.classList.add('hidden');
                if (errorText) errorText.textContent = '';
            };

            /**
             * Ambil pesan galat dari balasan JSON Laravel (422) dan ubah
             * menjadi daftar teks. `errors` berisi { field: [pesan, ...] },
             * sedangkan `message` adalah ringkasan umum.
             */
            const errorMessagesFrom = async (response) => {
                try {
                    const data = await response.json();
                    const errors = data?.errors ? Object.values(data.errors).flat() : [];

                    if (errors.length) return errors;
                    if (data?.message) return [data.message];
                } catch (e) {
                    // Balasan bukan JSON (mis. halaman HTML 419/500): biarkan
                    // pemanggil memakai pesan cadangan.
                }

                return ['Pesan gagal dikirim. Silakan coba lagi.'];
            };

            // Keadaan composer saat ini. `editState` berisi { id, url } bila
            // pengguna sedang menyunting pesan yang sudah ada; selama itu
            // composer mengirim PUT ke URL pesan, bukan POST pesan baru.
            let editState = null;

            const showComposerState = (text) => {
                if (!composerState) return;
                if (composerLabel) composerLabel.textContent = text;
                composerState.classList.remove('hidden');
            };

            const resetComposer = () => {
                editState = null;
                if (parentInput) parentInput.value = '';
                if (composerState) composerState.classList.add('hidden');
                if (submitButton) {
                    submitButton.title = 'Kirim pesan';
                    submitButton.setAttribute('aria-label', 'Kirim pesan');
                }
                if (attachmentBtn) attachmentBtn.classList.remove('hidden');
                clearFiles();
                clearErrors();
                if (form) form.action = form.dataset.storeUrl || form.action;
            };

            const enterReply = (id, author) => {
                editState = null;
                if (parentInput) parentInput.value = String(id);
                if (submitButton) submitButton.textContent = 'Kirim';
                if (attachmentBtn) attachmentBtn.classList.remove('hidden');
                if (form) form.action = form.dataset.storeUrl || form.action;
                showComposerState(`Membalas ${author}`);
                textarea?.focus();
            };

            const enterEdit = (node, url) => {
                editState = { id: node.dataset.messageId, url };
                if (parentInput) parentInput.value = '';
                if (form) form.action = url;
                if (submitButton) submitButton.textContent = 'Simpan';
                if (submitButton) {
                    submitButton.title = 'Simpan perubahan';
                    submitButton.setAttribute('aria-label', 'Simpan perubahan');
                }
                // Lampiran tidak dapat diubah setelah terkirim, jadi bagian
                // ini disembunyikan selama menyunting agar tidak menyesatkan.
                if (attachmentBtn) attachmentBtn.classList.add('hidden');
                clearFiles();
                if (textarea) textarea.value = node.querySelector('[data-chat-body]')?.textContent || '';
                showComposerState('Menyunting pesan');
                textarea?.focus();
            };

            document.querySelector('[data-chat-composer-cancel]')?.addEventListener('click', () => {
                resetComposer();
                if (textarea) textarea.value = '';
            });

            const jsonHeaders = {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            };

            // Satu listener untuk seluruh area pesan (delegasi), karena
            // pesan dapat bertambah lewat polling tanpa didaftarkan ulang.
            box.addEventListener('click', async (event) => {
                const actionButton = event.target.closest('[data-chat-action]');
                if (actionButton) {
                    const node = actionButton.closest('.chat-message');
                    const action = actionButton.dataset.chatAction;
                    const url = actionButton.dataset.url;

                    if (action === 'reply') {
                        enterReply(node.dataset.messageId, node.dataset.author || 'pesan ini');
                        return;
                    }

                    if (action === 'edit') {
                        enterEdit(node, url);
                        return;
                    }

                    if (action === 'delete') {
                        if (!window.confirm('Hapus pesan ini? Tindakan ini tidak dapat dibatalkan.')) return;
                        try {
                            const response = await fetch(url, {
                                method: 'POST',
                                headers: jsonHeaders,
                                body: new URLSearchParams({ _method: 'DELETE' }),
                            });
                            if (response.ok) {
                                node.remove();
                                if (editState && String(editState.id) === String(node.dataset.messageId)) {
                                    resetComposer();
                                    if (textarea) textarea.value = '';
                                }
                            }
                        } catch (e) { /* biarkan: percobaan berikutnya */ }
                        return;
                    }

                    if (action === 'pin') {
                        try {
                            const response = await fetch(url, {
                                method: 'POST',
                                headers: jsonHeaders,
                                body: new URLSearchParams({ _method: 'POST' }),
                            });
                            if (!response.ok) return;
                            const data = await response.json();
                            const pinned = !!data.pinned;
                            actionButton.dataset.pinned = pinned ? '1' : '0';
                            actionButton.classList.toggle('chat-action--active', pinned);
                            actionButton.title = pinned ? 'Lepas sematan' : 'Sematkan';
                            actionButton.setAttribute('aria-label', pinned ? 'Lepas sematan' : 'Sematkan pesan');
                            node.dataset.pinned = pinned ? '1' : '0';
                            updatePinFlag(node, pinned);
                            updatePinBar(node, pinned);
                        } catch (e) { /* biarkan */ }
                        return;
                    }
                }

                const openThread = event.target.closest('[data-chat-open-thread]');
                if (openThread) {
                    openThreadPanel(openThread.dataset.chatOpenThread);
                }
            });

            /* --------------------------------------------------------- */
            /* Bilah sematan                                             */
            /* --------------------------------------------------------- */

            const pinBar = document.querySelector('[data-chat-pinned-bar]');
            const pinList = pinBar?.querySelector('[data-chat-pinned-list]');
            const pinCount = pinBar?.querySelector('[data-chat-pinned-count]');

            const refreshPinBar = () => {
                if (!pinBar || !pinList) return;
                const items = pinList.querySelectorAll('[data-chat-jump]');
                if (pinCount) pinCount.textContent = String(items.length);
                pinBar.classList.toggle('hidden', items.length === 0);
            };

            // Tandai "disematkan" di deretan meta pesan tanpa memuat ulang.
            const updatePinFlag = (node, pinned) => {
                const flag = node.querySelector('.chat-flag--pinned');
                if (pinned && !flag) {
                    const meta = node.querySelector('.chat-meta');
                    if (!meta) return;
                    const span = document.createElement('span');
                    span.className = 'chat-flag chat-flag--pinned';
                    span.textContent = 'disematkan';
                    meta.append(span);
                } else if (!pinned && flag) {
                    flag.remove();
                }
            };

            // Menambah/menghapus satu baris sematan tanpa memuat ulang. Baris
            // baru ditaruh di atas, sama seperti urutan server (terbaru dulu).
            const updatePinBar = (node, pinned) => {
                if (!pinList || !node) return;
                const id = node.dataset.messageId;
                if (pinned) {
                    const li = document.createElement('li');
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'chat-pin-item';
                    button.dataset.chatJump = id;
                    const author = document.createElement('span');
                    author.className = 'chat-pin-author';
                    author.textContent = node.dataset.author || '';
                    const body = document.createElement('span');
                    body.className = 'chat-pin-body';
                    body.textContent = (node.querySelector('[data-chat-body]')?.textContent || '').slice(0, 140);
                    button.append(author, body);
                    li.append(button);
                    pinList.prepend(li);
                } else {
                    pinList.querySelector(`[data-chat-jump="${id}"]`)?.closest('li')?.remove();
                }
                refreshPinBar();
            };

            // Klik baris sematan -> gulir ke pesan aslinya dan sorot sekilas.
            onDocument('click', (event) => {
                const jump = event.target.closest('[data-chat-jump]');
                if (!jump) return;
                const target = box.querySelector(`.chat-message[data-message-id="${jump.dataset.chatJump}"]`);
                if (!target) return;
                target.scrollIntoView({ block: 'center' });
                target.classList.add('chat-message--flash');
                setTimeout(() => target.classList.remove('chat-message--flash'), 1200);
            });

            /* --------------------------------------------------------- */
            /* Panel utas                                                */
            /* --------------------------------------------------------- */

            const threadPanel = document.getElementById('chat-thread');
            const threadBody = threadPanel?.querySelector('[data-chat-thread-body]');
            const threadStatus = threadPanel?.querySelector('[data-chat-thread-status]');

            const openThreadPanel = async (messageId) => {
                if (!threadPanel || !threadBody) return;
                const template = box.dataset.threadUrlTemplate;
                if (!template) return;

                threadPanel.classList.remove('hidden');
                threadPanel.classList.add('flex');
                threadBody.innerHTML = '';
                if (threadStatus) {
                    threadStatus.classList.remove('hidden');
                    threadStatus.textContent = 'Memuat utas...';
                }

                try {
                    const response = await fetch(template.replace('__ID__', messageId), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!response.ok) throw new Error('gagal');
                    const data = await response.json();
                    threadBody.innerHTML = data.html || '';
                    if (threadStatus) threadStatus.classList.add('hidden');
                    if (!data.count) {
                        const empty = document.createElement('p');
                        empty.className = 'px-4 py-6 text-center text-sm text-ink-500';
                        empty.textContent = 'Belum ada balasan pada pesan ini.';
                        threadBody.append(empty);
                    }
                } catch (e) {
                    if (threadStatus) {
                        threadStatus.classList.remove('hidden');
                        threadStatus.textContent = 'Gagal memuat utas. Coba lagi.';
                    }
                }
            };

            threadPanel?.querySelector('[data-chat-thread-close]')?.addEventListener('click', () => {
                threadPanel.classList.add('hidden');
                threadPanel.classList.remove('flex');
                if (threadBody) threadBody.innerHTML = '';
            });

            /* --------------------------------------------------------- */
            /* Pencarian pesan (PRD §15)                                 */
            /* --------------------------------------------------------- */

            const searchInput = document.getElementById('chat-search');
            const searchClear = document.querySelector('[data-chat-search-clear]');
            // Simpan HTML riwayat supaya pencarian dapat dibatalkan tanpa
            // memuat ulang halaman (dan tanpa kehilangan posisi gulir).
            let historyHtml = null;
            let searchTimer = null;

            const cancelSearch = () => {
                if (historyHtml !== null) {
                    box.innerHTML = historyHtml;
                    historyHtml = null;
                }
                searchClear?.classList.add('hidden');
            };

            const runSearch = async () => {
                const term = (searchInput?.value || '').trim();
                if (!term) {
                    cancelSearch();
                    return;
                }
                if (historyHtml === null) historyHtml = box.innerHTML;
                try {
                    const url = new URL(searchInput.dataset.searchUrl, window.location.origin);
                    url.searchParams.set('q', term);
                    const response = await fetch(url, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!response.ok) return;
                    const data = await response.json();
                    box.innerHTML = data.html
                        || '<p class="px-4 py-8 text-center text-sm text-ink-500">Tidak ada pesan yang cocok.</p>';
                    searchClear?.classList.remove('hidden');
                } catch (e) { /* biarkan */ }
            };

            searchInput?.addEventListener('input', () => {
                if (searchTimer) clearTimeout(searchTimer);
                searchTimer = setTimeout(runSearch, 300);
            });

            searchClear?.addEventListener('click', () => {
                if (searchInput) searchInput.value = '';
                cancelSearch();
            });

            // Saat pengguna turun ke bawah, lompatan ditenggang pesan baru
            // dianggap selesai dibaca: sembunyikan tombol dan reset hitungan.
            box.addEventListener('scroll', () => {
                if (atBottom()) {
                    unreadCount = 0;
                }
                updateNewButton();
            });

            newButton?.addEventListener('click', () => {
                scrollToBottom();
                unreadCount = 0;
                updateNewButton();
                form?.querySelector('textarea[name="body"]')?.focus();
            });

            scrollToBottom();

            const poll = async () => {
                if (document.hidden) return;
                try {
                    const response = await fetch(`${pollUrl}?after=${lastId}`, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!response.ok) return;

                    const data = await response.json();
                    if (data.count <= 0) return;

                    const wasAtBottom = atBottom();
                    clearEmptyState();

                    // Parse dulu agar tiap pesan bisa dipasangkan dengan draf
                    // dan disisipkan pada posinya (A3), bukan dibubuhkan
                    // begitu saja di akhir.
                    const template = document.createElement('template');
                    template.innerHTML = data.html;

                    // Partial tak tahu pesan sebelumnya saat polling, jadi
                    // tiap batch datang membawa pemisah hari sendiri. Yang
                    // sudah ada di box jangan ditulis dua kali (A9).
                    let lastDate = null;
                    const existing = box.querySelectorAll('.chat-message');
                    if (existing.length) {
                        lastDate = existing[existing.length - 1].dataset.date;
                    }

                    Array.from(template.content.children).forEach((node) => {
                        if (node.classList.contains('chat-day-separator')) {
                            if (node.dataset.date === lastDate) {
                                return;
                            }
                            box.append(node);
                            lastDate = node.dataset.date;
                            return;
                        }

                        const body = (node.querySelector('[data-draft-body]')?.textContent || '').trim();
                        const index = pendingDrafts.findIndex((draft) => draft.body === body);

                        if (index !== -1) {
                            pendingDrafts[index].node.replaceWith(node);
                            pendingDrafts.splice(index, 1);
                        } else {
                            box.append(node);
                        }
                        lastDate = node.dataset.date;

                        // Pesan sendiri tidak dihitung sebagai pesan baru yang
                        // belum dibaca (A2), walau datang lewat polling.
                        if (node.dataset.senderId !== currentUserId) {
                            unreadCount += 1;
                        }
                    });

                    lastId = data.last_id;
                    box.dataset.lastId = lastId;

                    if (wasAtBottom) {
                        scrollToBottom();
                        unreadCount = 0;
                    }
                    updateNewButton();
                } catch (e) {
                    // Gagal poll bukan kejadian fatal: percobaan berikutnya
                    // akan mencoba lagi. Sengaja tanpa notifikasi agar tidak
                    // berisik ketika koneksi hanya sekadar tersendat.
                }
            };

            if (form) {
                // A4: Enter kirim pesan, Shift+Enter sisipkan baris baru.
                // Dikesampingkan saat input compose (IME) sedang aktif.
                textarea?.addEventListener('keydown', (event) => {
                    if (event.key !== 'Enter' || event.shiftKey || event.isComposing) {
                        return;
                    }
                    event.preventDefault();
                    form.requestSubmit();
                });

                // Kirim pesan lewat fetch supaya halaman tidak berkedip.
                //
                // PENTING: jalur sukses harus bebas dari reload. Pengiriman
                // (jaringan) sengaja DIPISAH dari pembaruan tampilan, supaya
                // kegagalan menggambar ulang tidak pernah jatuh ke jalur
                // `form.submit()` — itu akan memuat ulang halaman dan
                // menghapus berkas yang sudah dipilih pengguna.
                form.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    const button = form.querySelector('button[type="submit"]');
                    const sentBody = textarea?.value ?? '';
                    const hasAttachments = form.querySelector('input[type="file"]')?.files?.length > 0;
                    const editing = editState !== null;

                    clearErrors();

                    // Saat MENYUNTING: kirim PUT ke URL pesan, lalu ganti isi
                    // gelembung di tempat. Tidak ada draf optimis dan tidak
                    // ada poll: hasil suntingan datang langsung dari respons.
                    if (editing) {
                        const editId = editState.id;
                        const editUrl = editState.url;

                        button.disabled = true;

                        let response;
                        try {
                            const body = new FormData();
                            body.append('_method', 'PUT');
                            body.append('body', sentBody);
                            response = await fetch(editUrl, {
                                method: 'POST',
                                body,
                                headers: jsonHeaders,
                            });
                        } catch (e) {
                            // Jaringan mati (bukan penolakan server): baru di
                            // sini pengiriman biasa dipakai, supaya pesan tidak
                            // hilang begitu saja.
                            button.disabled = false;
                            form.submit();
                            return;
                        }

                        if (!response.ok) {
                            // Ditolak server (mis. validasi). Galat ditampilkan
                            // di dalam halaman, tanpa memuat ulang.
                            button.disabled = false;
                            showErrors(await errorMessagesFrom(response));
                            return;
                        }

                        // Mulai titik ini pesan SUDAH tersimpan: tidak ada lagi
                        // `form.submit()`. Kegagalan tampilan hanya menutup
                        // dengan menyegarkan daftar lewat poll.
                        try {
                            const data = await response.json();
                            const target = box.querySelector(`.chat-message[data-message-id="${editId}"]`);
                            if (target && data.html) {
                                const holder = document.createElement('div');
                                holder.innerHTML = data.html;
                                const replacement = holder.querySelector('.chat-message');
                                if (replacement) target.replaceWith(replacement);
                            }
                            resetComposer();
                            if (textarea) {
                                textarea.value = '';
                                autoResizeTextarea();
                            }
                        } catch (e) {
                            resetComposer();
                            poll();
                        } finally {
                            button.disabled = false;
                        }

                        return;
                    }

                    // Snapshot formulir SEBELUM textarea dikosongkan untuk draf
                    // optimis. `FormData(form)` membekukan nilai kontrol saat
                    // dibuat; bila snapshot dibuat setelah textarea
                    // dikosongkan, `body` terkirim KOSONG dan server menolak
                    // dengan "Pesan tidak boleh kosong." — akibatnya pesan
                    // tidak pernah terkirim. `payload.set('body', ...)` mengikat
                    // nilai yang sudah ditangkap, sehingga urutan baris di bawah
                    // tidak lagi menentukan isi yang dikirim.
                    const payload = new FormData(form);
                    payload.set('body', sentBody);

                    // Lampiran tidak bisa dirender optimis (A3), jadi pesan
                    // dengan lampiran menunggu sampai poll menjawab.
                    if (sentBody.trim() && !hasAttachments) {
                        insertDraft(sentBody.trim());
                        textarea.value = '';
                        autoResizeTextarea();
                    }

                    button.disabled = true;

                    let response;
                    try {
                        response = await fetch(form.action, {
                            method: 'POST',
                            body: payload,
                            headers: jsonHeaders,
                        });
                    } catch (e) {
                        // Jaringan mati (bukan penolakan server): kembalikan
                        // draf lalu pakai pengiriman biasa agar tidak hilang.
                        removeDraft(sentBody.trim());
                        if (textarea) textarea.value = sentBody;
                        button.disabled = false;
                        form.submit();
                        return;
                    }

                    if (!response.ok) {
                        // Ditolak server (mis. 422 validasi). Draf dibatalkan
                        // dan teks dikembalikan, tetapi halaman TIDAK dimuat
                        // ulang supaya lampiran tetap menempel.
                        removeDraft(sentBody.trim());
                        if (textarea) textarea.value = sentBody;
                        button.disabled = false;
                        showErrors(await errorMessagesFrom(response));
                        return;
                    }

                    // Pesan SUDAH tersimpan di server. Sisa langkah hanya
                    // urusan tampilan, jadi tidak boleh memicu reload.
                    try {
                        form.reset();
                        autoResizeTextarea();
                        resetComposer();
                        await poll();
                        scrollToBottom();
                    } catch (e) {
                        // Abaikan: pesan tetap terkirim dan akan tampil pada
                        // polling berikutnya.
                    } finally {
                        button.disabled = false;
                    }
                });
            }

            let timer = null;
            const schedule = () => {
                if (timer) clearInterval(timer);
                timer = null;
                if (document.hidden) return;
                timer = setInterval(poll, interval);
            };

            onDocument('visibilitychange', () => {
                schedule();
                if (!document.hidden) poll();
            });

            // Berpindah kembali dari jendela lain (mis. dua jendela
            // berdampingan) belum tentu mengubah visibilitas tab, padahal pesan
            // yang masuk saat jendela tidak aktif baru terlihat sekarang.
            // `focus` pada `window` menutup celah itu tanpa memperpendek jeda
            // tetap — polling pesan sengaja TIDAK memakai backoff karena di
            // halaman inilah pengguna memang menunggu balasan.
            onWindow('focus', () => {
                if (!document.hidden) poll();
            });

            schedule();

            // Pembersih yang dijalankan SEBELUM area percakapan ditukar saat
            // berpindah chat. Yang perlu dilepas manual hanya yang hidup di
            // LUAR area percakapan: `setInterval` polling dan listener tingkat
            // `document`/`window`. Listener pada elemen di dalam area (mis.
            // `box`, `form`) ikut mati bersama elemennya, jadi tidak didaftar.
            return () => {
                if (timer) clearInterval(timer);
                timer = null;
                if (searchTimer) clearTimeout(searchTimer);
                globalListeners.forEach((off) => off());
            };
            };

            /* --------------------------------------------------------- */
            /* Berpindah percakapan tanpa memuat ulang (pushState)       */
            /* --------------------------------------------------------- */

            const chatMain = () => document.querySelector('[data-chat-main]');

            /**
             * Tandai baris yang sedang dibuka di panel samping.
             *
             * Panel sengaja TIDAK ikut ditukar, jadi penandanya harus
             * diperbarui di sini. Badge "N baru" milik percakapan yang baru
             * dibuka juga dihapus: server menandainya sudah dibaca saat
             * halaman dimuat (markRead), sehingga membiarkan badge tetap ada
             * akan bertentangan dengan isi halaman yang sedang dilihat.
             */
            const markActiveConversation = (url) => {
                const path = new URL(url, window.location.origin).pathname;

                document.querySelectorAll('[data-chat-conversation-link]').forEach((link) => {
                    const isActive = new URL(link.href, window.location.origin).pathname === path;
                    const unreadBadge = link.querySelector('[data-chat-unread]');

                    // Sorotan aktif dan sorotan "belum dibaca" memakai warna
                    // yang sama tetapi berbeda kekuatan. Baris lain TIDAK
                    // boleh kehilangan sorotan belum-dibacanya hanya karena
                    // baris ini dibuka.
                    link.classList.toggle('bg-brand-50/60', isActive);
                    link.classList.toggle('bg-brand-50/30', !isActive && unreadBadge !== null);

                    if (isActive) {
                        link.setAttribute('aria-current', 'true');
                        unreadBadge?.remove();
                    } else {
                        link.removeAttribute('aria-current');
                    }
                });
            };

            // Penanda urutan permintaan. Bila pengguna mengklik dua percakapan
            // beruntun, respons yang tiba belakangan belum tentu milik klik
            // yang terakhir. Tanpa penjaga ini, URL dan judul dapat menunjuk
            // percakapan B sementara isi area masih percakapan A.
            let swapToken = 0;

            const swapTo = async (url, { push = true } = {}) => {
                const current = chatMain();
                if (!current) {
                    window.location.href = url;
                    return;
                }

                const token = ++swapToken;
                current.setAttribute('aria-busy', 'true');

                let fresh = null;
                try {
                    const response = await fetch(url, {
                        headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!response.ok) throw new Error('gagal');

                    const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
                    fresh = doc.querySelector('[data-chat-main]');
                    if (!fresh) throw new Error('region tidak ditemukan');

                    // Hasil kedaluwarsa (ada klik yang lebih baru): buang,
                    // biar permintaan terakhir yang menang.
                    if (token !== swapToken) return;

                    // Bersihkan dulu, baru tukar: bila pembersihan dijalankan
                    // setelah tukar, `initChat()` yang baru sempat berjalan
                    // berdampingan dengan timer lama.
                    teardown?.();
                    teardown = null;

                    current.replaceWith(fresh);
                    document.title = doc.title;
                } catch (e) {
                    // Permintaan yang lebih baru sudah menangani navigasinya
                    // sendiri; jangan menimpanya dengan lompatan halaman ini.
                    if (token !== swapToken) return;

                    // Gagal (jaringan/HTML tak terduga): jatuh ke navigasi
                    // biasa supaya pengguna tetap sampai di percakapan tujuan.
                    window.location.href = url;
                    return;
                }

                if (push) {
                    history.pushState({ chat: true }, '', url);
                }

                markActiveConversation(url);

                // Percakapan yang baru dibuka sudah ditandai dibaca di server,
                // jadi badge sidebar boleh turun SEKARANG — tanpa menunggu satu
                // siklus polling badge (PRD Chat §9).
                window.fsbRefreshBadges?.();

                teardown = initChat();

                // Komponen Alpine di dalam area yang baru datang (modal
                // anggota, dsb) belum dikenal Alpine: `Alpine.start()` hanya
                // berjalan sekali di app.js, jadi subtree baru harus
                // diinisialisasi manual.
                window.Alpine?.initTree?.(fresh);
            };

            // Klik baris percakapan di panel samping.
            //
            // Listener ini hidup selama halaman terbuka (bukan per-percakapan):
            // yang diklik adalah panel, dan panel tidak pernah ditukar.
            document.addEventListener('click', (event) => {
                const link = event.target.closest('[data-chat-conversation-link]');
                if (!link) return;

                // Klik bermodifikasi (tab baru, unduh, dsb) tetap ditangani
                // browser — jangan direbut.
                if (event.defaultPrevented || event.button !== 0
                    || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                    return;
                }

                event.preventDefault();
                swapTo(link.href);
            });

            // Tombol back/forward: entri yang kita dorong lewat pushState
            // adalah entri dokumen yang sama, sehingga kembali ke percakapan
            // sebelumnya memicu popstate (bukan pemuatan ulang).
            document.addEventListener('popstate', () => {
                if (!chatMain()) {
                    window.location.reload();
                    return;
                }

                swapTo(window.location.href, { push: false });
            });

            teardown = initChat();
        })();
    </script>
@endpush
