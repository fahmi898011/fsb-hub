<div class="space-y-4">
    <div>
        <label class="form-label">Nama kategori</label>
        <input class="form-input w-full" name="name" required maxlength="150"
               value="{{ old('name', $qaAssessmentCategory->name ?? '') }}">
        @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="form-label">Deskripsi</label>
        <textarea class="form-input w-full" name="description" rows="3">{{ old('description', $qaAssessmentCategory->description ?? '') }}</textarea>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="form-label">Urutan</label>
            <input class="form-input w-full" type="number" name="sort_order" min="0"
                   value="{{ old('sort_order', $qaAssessmentCategory->sort_order ?? 0) }}">
        </div>
        <div>
            <label class="form-label">Status</label>
            <select class="form-input w-full" name="status">
                <option value="active" @selected(old('status', $qaAssessmentCategory->status ?? 'active') === 'active')>Aktif</option>
                <option value="inactive" @selected(old('status', $qaAssessmentCategory->status ?? '') === 'inactive')>Nonaktif</option>
            </select>
        </div>
    </div>
</div>
