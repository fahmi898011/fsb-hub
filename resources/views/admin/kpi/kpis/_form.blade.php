{{-- Form KPI: dipakai halaman tambah & edit. --}}
{{-- AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master --}}
<div class="space-y-5">
    <div>
        <label for="name" class="form-label">Nama KPI <span class="text-red-500">*</span></label>
        <input id="name" name="name" type="text" value="{{ old('name', $kpi->name ?? '') }}" required class="form-input" placeholder="mis. Quality Score">
        @error('name')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="department_id" class="form-label">Divisi</label>
            <select id="department_id" name="department_id" class="form-input">
                <option value="">— tanpa divisi —</option>
                @foreach ($divisions as $division)
                    <option value="{{ $division->id }}" @selected((int) old('department_id', $kpi->department_id ?? 0) === $division->id)>{{ $division->name }}</option>
                @endforeach
            </select>
            @error('department_id')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="code" class="form-label">Kode</label>
            <input id="code" name="code" type="text" value="{{ old('code', $kpi->code ?? '') }}" class="form-input" placeholder="mis. QUALITY">
            @error('code')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div>
        <label for="description" class="form-label">Deskripsi</label>
        <textarea id="description" name="description" rows="3" class="form-input">{{ old('description', $kpi->description ?? '') }}</textarea>
        @error('description')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="sort_order" class="form-label">Urutan</label>
            <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $kpi->sort_order ?? 0) }}" class="form-input">
            @error('sort_order')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="status" class="form-label">Status <span class="text-red-500">*</span></label>
            <select id="status" name="status" required class="form-input">
                <option value="active" @selected(old('status', $kpi->status ?? 'active') === 'active')>Aktif</option>
                <option value="inactive" @selected(old('status', $kpi->status ?? '') === 'inactive')>Nonaktif</option>
            </select>
            @error('status')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>
</div>
