{{-- Form Divisi: dipakai halaman tambah & edit. --}}
@php($isEdit = $department->exists)

<div class="space-y-5">
    <div>
        <label for="name" class="form-label">Nama Divisi <span class="text-red-500">*</span></label>
        <input id="name" name="name" type="text" value="{{ old('name', $department->name ?? '') }}" required class="form-input" placeholder="mis. Human Resources, Finance">
        @error('name')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="code" class="form-label">Kode</label>
            <input id="code" name="code" type="text" value="{{ old('code', $department->code ?? '') }}" class="form-input" placeholder="mis. HR, FIN">
            <p class="mt-1 text-xs text-ink-400">Singkatan divisi (opsional), maksimal 10 karakter.</p>
            @error('code')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="sort_order" class="form-label">Urutan</label>
            <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $department->sort_order ?? 0) }}" class="form-input">
            <p class="mt-1 text-xs text-ink-400">Divisi dengan urutan kecil tampil lebih dulu.</p>
            @error('sort_order')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div>
        <label for="description" class="form-label">Deskripsi</label>
        <textarea id="description" name="description" rows="3" class="form-input">{{ old('description', $department->description ?? '') }}</textarea>
        @error('description')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="status" class="form-label">Status <span class="text-red-500">*</span></label>
        <select id="status" name="status" required class="form-input">
            <option value="active" @selected(old('status', $department->status ?? 'active') === 'active')>Aktif</option>
            <option value="inactive" @selected(old('status', $department->status ?? '') === 'inactive')>Nonaktif</option>
        </select>
        <p class="mt-1 text-xs text-ink-400">Divisi nonaktif tidak dipakai lagi untuk karyawan baru.</p>
        @error('status')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
</div>
