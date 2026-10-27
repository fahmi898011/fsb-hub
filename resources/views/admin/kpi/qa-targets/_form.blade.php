{{-- Form Target Sampling: dipakai halaman tambah & edit. --}}
{{-- AGENT: asisten | 2026-10-04 | buat berkas baru | modul KPI, fase 3 QA reporting --}}
<div class="space-y-5">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="department_id" class="form-label">Divisi</label>
            <select id="department_id" name="department_id" class="form-input">
                <option value="">Semua divisi (aturan umum)</option>
                @foreach ($divisions as $division)
                    <option value="{{ $division->id }}" @selected((string) old('department_id', $target->department_id) === (string) $division->id)>{{ $division->name }}</option>
                @endforeach
            </select>
            @error('department_id')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="scorecard_id" class="form-label">Scorecard</label>
            <select id="scorecard_id" name="scorecard_id" class="form-input">
                <option value="">Semua scorecard (aturan umum)</option>
                @foreach ($scorecards as $scorecard)
                    <option value="{{ $scorecard->id }}" @selected((string) old('scorecard_id', $target->scorecard_id) === (string) $scorecard->id)>{{ $scorecard->name }}</option>
                @endforeach
            </select>
            @error('scorecard_id')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <p class="text-xs text-ink-400">
        Membiarkan Divisi dan Scorecard kosong berarti aturan berlaku untuk semuanya. Aturan yang lebih spesifik
        (memilih divisi dan/atau scorecard) dipakai lebih dulu oleh laporan.
    </p>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="frequency" class="form-label">Frekuensi <span class="text-red-500">*</span></label>
            <select id="frequency" name="frequency" required class="form-input">
                @foreach (['weekly' => 'Mingguan', 'monthly' => 'Bulanan'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('frequency', $target->frequency ?? 'weekly') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('frequency')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="target_type" class="form-label">Jenis Target <span class="text-red-500">*</span></label>
            <select id="target_type" name="target_type" required class="form-input">
                @foreach (['per_employee' => 'Per Karyawan', 'per_qa' => 'Per QA', 'per_team' => 'Per Tim', 'per_division' => 'Per Divisi', 'total' => 'Total Mingguan'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('target_type', $target->target_type ?? 'per_employee') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('target_type')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div>
            <label for="target_value" class="form-label">Nilai Target <span class="text-red-500">*</span></label>
            <input id="target_value" name="target_value" type="number" min="1" max="10000" required
                   value="{{ old('target_value', $target->target_value ?? 5) }}" class="form-input">
            <p class="mt-1 text-xs text-ink-400">Jumlah sample per unit pada Jenis Target di atas.</p>
            @error('target_value')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="effective_date" class="form-label">Berlaku Sejak <span class="text-red-500">*</span></label>
            <input id="effective_date" name="effective_date" type="date" required
                   value="{{ old('effective_date', $target->effective_date?->format('Y-m-d') ?? now()->toDateString()) }}" class="form-input">
            @error('effective_date')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="status" class="form-label">Status <span class="text-red-500">*</span></label>
            <select id="status" name="status" required class="form-input">
                <option value="active" @selected(old('status', $target->status ?? 'active') === 'active')>Aktif</option>
                <option value="inactive" @selected(old('status', $target->status ?? '') === 'inactive')>Nonaktif</option>
            </select>
            @error('status')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div>
        <label for="notes" class="form-label">Catatan</label>
        <textarea id="notes" name="notes" rows="3" class="form-input">{{ old('notes', $target->notes ?? '') }}</textarea>
        @error('notes')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
</div>
