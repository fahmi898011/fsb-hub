<div class="space-y-4">
    <div>
        <label class="form-label">Nama periode</label>
        <input class="form-input w-full" name="name" required maxlength="150"
               value="{{ old('name', $qaPeriod->name ?? '') }}" placeholder="mis. Januari 2026">
        @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="form-label">Mulai</label>
            <input class="form-input w-full" type="date" name="start_date" required
                   value="{{ old('start_date', isset($qaPeriod) ? $qaPeriod->start_date->format('Y-m-d') : '') }}">
            @error('start_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="form-label">Selesai</label>
            <input class="form-input w-full" type="date" name="end_date" required
                   value="{{ old('end_date', isset($qaPeriod) ? $qaPeriod->end_date->format('Y-m-d') : '') }}">
            @error('end_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="form-label">Periode induk (opsional)</label>
            <select class="form-input w-full" name="parent_period_id">
                <option value="">Tanpa induk</option>
                @foreach ($parents as $parent)
                    <option value="{{ $parent->id }}" @selected((int) old('parent_period_id', $qaPeriod->parent_period_id ?? null) === $parent->id)>{{ $parent->name }}</option>
                @endforeach
            </select>
            @error('parent_period_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="form-label">Status</label>
            <select class="form-input w-full" name="status">
                <option value="active" @selected(old('status', $qaPeriod->status ?? 'active') === 'active')>Aktif</option>
                <option value="inactive" @selected(old('status', $qaPeriod->status ?? '') === 'inactive')>Nonaktif</option>
            </select>
        </div>
    </div>

    <div>
        <label class="form-label">Deskripsi</label>
        <textarea class="form-input w-full" name="description" rows="3">{{ old('description', $qaPeriod->description ?? '') }}</textarea>
    </div>
</div>
