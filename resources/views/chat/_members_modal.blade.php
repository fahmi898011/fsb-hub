{{-- Kelola anggota kanal (manual — PRD §6). Hanya dirender untuk admin/qa.
     Variabel: $conversation, $candidates. --}}

<x-modal name="channel-members" maxWidth="lg" focusable>
    <div class="p-6">
        <h2 class="text-base font-semibold text-ink-900">Kelola Anggota</h2>
        <p class="mt-0.5 text-sm text-ink-500">
            Keanggotaan bersifat manual. Divisi hanya acuan saat memilih anggota.
        </p>

        {{-- Daftar anggota saat ini --}}
        <div class="mt-5">
            <span class="form-label">Anggota saat ini ({{ $conversation->members->count() }})</span>

            <div class="divide-y divide-ink-100 rounded-xl border border-ink-200">
                @foreach ($conversation->members->sortBy('name') as $member)
                    <div class="flex items-center gap-3 px-3 py-2.5">
                        <img src="{{ $member->avatar_url }}" alt="" class="h-8 w-8 rounded-full object-cover ring-1 ring-ink-200">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm text-ink-800">{{ $member->name }}</p>
                            <p class="truncate text-xs text-ink-400">{{ $member->departmentName() ?: 'Tanpa divisi' }}</p>
                        </div>

                        @if ($member->id === $conversation->created_by)
                            <x-badge variant="gray">Pembuat</x-badge>
                        @else
                            <form method="POST" action="{{ route('chat.members.destroy', [$conversation, $member]) }}"
                                  onsubmit="return confirm('Keluarkan {{ $member->name }} dari kanal ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-ghost btn-sm text-red-600 hover:bg-red-50">Keluarkan</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Tambah anggota --}}
        @if ($candidates->isNotEmpty())
            <form method="POST" action="{{ route('chat.members.store', $conversation) }}" class="mt-5">
                @csrf

                <span class="form-label">Tambah anggota</span>
                <div class="max-h-48 space-y-1 overflow-y-auto rounded-xl border border-ink-200 p-2">
                    @foreach ($candidates as $candidate)
                        <label class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 hover:bg-ink-50">
                            <input type="checkbox" name="members[]" value="{{ $candidate->id }}"
                                   class="rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                            <span class="text-sm text-ink-700">{{ $candidate->name }}</span>
                            <span class="ml-auto text-xs text-ink-400">{{ $candidate->departmentName() }}</span>
                        </label>
                    @endforeach
                </div>
                @error('members')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror

                <div class="mt-3 flex justify-end">
                    <button type="submit" class="btn-primary btn-sm">Tambahkan</button>
                </div>
            </form>
        @else
            <p class="mt-5 text-sm text-ink-400">Semua karyawan aktif sudah menjadi anggota kanal ini.</p>
        @endif

        <div class="mt-6 flex justify-end">
            <button type="button" class="btn-secondary" @click="$dispatch('close-modal', 'channel-members')">Tutup</button>
        </div>
    </div>
</x-modal>
