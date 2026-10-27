{{-- Form Kategori: dipakai halaman tambah & edit. --}}
{{-- AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master --}}
<div class="space-y-5">
    <div>
        <label for="name" class="form-label">Nama Kategori <span class="text-red-500">*</span></label>
        <input id="name" name="name" type="text" value="{{ old('name', $category->name ?? '') }}" required class="form-input" placeholder="mis. Opening & Closing">
        @error('name')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="description" class="form-label">Deskripsi</label>
        <textarea id="description" name="description" rows="3" class="form-input">{{ old('description', $category->description ?? '') }}</textarea>
        @error('description')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="sort_order" class="form-label">Urutan</label>
            <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $category->sort_order ?? 0) }}" class="form-input">
            @error('sort_order')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="status" class="form-label">Status <span class="text-red-500">*</span></label>
            <select id="status" name="status" required class="form-input">
                <option value="active" @selected(old('status', $category->status ?? 'active') === 'active')>Aktif</option>
                <option value="inactive" @selected(old('status', $category->status ?? '') === 'inactive')>Nonaktif</option>
            </select>
            @error('status')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>
</div>
