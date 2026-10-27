{{-- Form Tim: dipakai halaman tambah & edit.
     NB: JANGAN menulis token direktif PHP (mis. blok buka/tutup PHP) di dalam
     komentar ini. Blade menyimpan blok PHP mentah SEBELUM komentar dibuang,
     sehingga regex blok PHP akan cocok ke token di dalam komentar lebih dulu,
     placeholder-nya ikut terhapus, dan blok PHP asli di bawah ini hilang
     (lihat progres.md, "jebakan Blade"). --}}
@php
    $isEdit = $team->exists;
@endphp

<div class="space-y-5">
    <div>
        <label for="name" class="form-label">Nama Tim <span class="text-red-500">*</span></label>
        <input id="name" name="name" type="text" value="{{ old('name', $team->name ?? '') }}" required class="form-input" placeholder="mis. QA Core, CC Reguler">
        @error('name')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="department_id" class="form-label">Divisi</label>
            <select id="department_id" name="department_id" class="form-input">
                <option value="">— Tanpa divisi —</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected((string) old('department_id', $team->department_id ?? '') === (string) $department->id)>
                        {{ $department->name }}
                    </option>
                @endforeach
            </select>
            @error('department_id')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="leader_id" class="form-label">Ketua Tim</label>
            <select id="leader_id" name="leader_id" class="form-input">
                <option value="">— Belum ditentukan —</option>
                @foreach ($candidates as $candidate)
                    <option value="{{ $candidate->id }}" @selected((string) old('leader_id', $team->leader_id ?? '') === (string) $candidate->id)>
                        {{ $candidate->name }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-ink-400">Ketua otomatis menjadi anggota tim ini.</p>
            @error('leader_id')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div>
        <label for="description" class="form-label">Deskripsi</label>
        <textarea id="description" name="description" rows="2" class="form-input">{{ old('description', $team->description ?? '') }}</textarea>
        @error('description')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="status" class="form-label">Status <span class="text-red-500">*</span></label>
        <select id="status" name="status" required class="form-input">
            <option value="active" @selected(old('status', $team->status ?? 'active') === 'active')>Aktif</option>
            <option value="inactive" @selected(old('status', $team->status ?? '') === 'inactive')>Nonaktif</option>
        </select>
        @error('status')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <span class="form-label">Anggota Tim</span>

        @if ($availableUsers->isEmpty())
            <p class="rounded-lg bg-ink-50 px-3 py-2 text-sm text-ink-500">
                Tidak ada karyawan yang tersedia. Semua karyawan sudah tergabung di tim lain.
            </p>
        @else
            @php
            // Anggota yang sudah terpilih: dari input lama bila validasi gagal,
            // jika tidak dari relasi tim.
            $selectedMembers = collect(old('members', $isEdit ? $team->members->pluck('id')->all() : []))
                ->map(fn ($id) => (int) $id)
                ->all();
        @endphp

        <div class="max-h-72 space-y-1 overflow-y-auto rounded-lg border border-ink-200 p-2">
                @foreach ($availableUsers as $user)
                    <label class="flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2 hover:bg-ink-50">
                        <input type="checkbox" name="members[]" value="{{ $user->id }}"
                               @checked(in_array($user->id, $selectedMembers, true))
                               class="h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm text-ink-800">{{ $user->name }}</span>
                            <span class="block truncate text-xs text-ink-400">{{ $user->email }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            <p class="mt-1 text-xs text-ink-400">
                Satu karyawan hanya boleh berada di satu tim. Karyawan yang sudah tergabung di tim lain tidak muncul di sini.
            </p>
        @endif

        @error('members')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        @error('members.*')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
</div>
