{{-- Form Scorecard: dipakai halaman tambah & edit. --}}
{{-- AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master --}}
<div class="space-y-5">
    <div>
        <label for="name" class="form-label">Nama Scorecard <span class="text-red-500">*</span></label>
        <input id="name" name="name" type="text" value="{{ old('name', $scorecard->name ?? '') }}" required class="form-input" placeholder="mis. QA Scorecard - Quality Inbound">
        @error('name')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="kpi_id" class="form-label">KPI <span class="text-red-500">*</span></label>
            <select id="kpi_id" name="kpi_id" required class="form-input">
                <option value="">— pilih KPI —</option>
                @foreach ($kpis as $kpi)
                    <option value="{{ $kpi->id }}" @selected((int) old('kpi_id', $scorecard->kpi_id ?? 0) === $kpi->id)>{{ $kpi->name }}</option>
                @endforeach
            </select>
            @error('kpi_id')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="channel_id" class="form-label">Channel</label>
            <select id="channel_id" name="channel_id" class="form-input">
                <option value="">— tanpa channel —</option>
                @foreach ($channels as $channel)
                    <option value="{{ $channel->id }}" @selected((int) old('channel_id', $scorecard->channel_id ?? 0) === $channel->id)>{{ $channel->name }}</option>
                @endforeach
            </select>
            @error('channel_id')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div>
        <label for="description" class="form-label">Deskripsi</label>
        <textarea id="description" name="description" rows="3" class="form-input">{{ old('description', $scorecard->description ?? '') }}</textarea>
        @error('description')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="sort_order" class="form-label">Urutan</label>
            <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $scorecard->sort_order ?? 0) }}" class="form-input">
            @error('sort_order')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="status" class="form-label">Status <span class="text-red-500">*</span></label>
            <select id="status" name="status" required class="form-input">
                @foreach (['draft' => 'Draft', 'active' => 'Aktif', 'inactive' => 'Nonaktif', 'archived' => 'Arsip'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $scorecard->status ?? 'draft') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('status')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>
</div>
