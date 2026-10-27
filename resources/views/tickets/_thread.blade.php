{{-- Utas percakapan tiket, dipakai halaman karyawan & halaman IT.
     Perbedaan satu-satunya: halaman karyawan tidak pernah menerima catatan
     internal (disaring di controller), jadi partial ini cukup merender apa
     yang diberikan. Dipanggil via @include sehingga menerima $replies. --}}

@if ($replies->isEmpty())
    <p class="px-5 py-6 text-center text-sm text-ink-400">Belum ada balasan.</p>
@else
    <div class="divide-y divide-ink-100">
        @foreach ($replies as $reply)
            @php($isInternal = (bool) $reply->is_internal)

            <div class="flex gap-3 px-5 py-4 {{ $isInternal ? 'bg-amber-50/50' : '' }}">
                <img src="{{ $reply->user?->avatar_url }}" alt="" class="mt-0.5 h-8 w-8 shrink-0 rounded-full object-cover ring-1 ring-ink-200">

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-sm font-semibold text-ink-900">{{ $reply->user?->name ?? 'Pengguna dihapus' }}</p>
                        @if ($isInternal)
                            <x-badge variant="amber">Catatan Internal</x-badge>
                        @endif
                        <span class="text-xs text-ink-400">{{ $reply->created_at?->format('d M Y H:i') }}</span>
                    </div>

                    <p class="mt-1.5 whitespace-pre-line break-words text-sm text-ink-700">{{ $reply->message }}</p>
                </div>
            </div>
        @endforeach
    </div>
@endif
