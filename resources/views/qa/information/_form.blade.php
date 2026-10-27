@php
    $isEdit = $item !== null;
    $selectedCategory = old('category_id', $item->category_id ?? null);
    $tagsValue = old('tags', $isEdit ? $item->tags->pluck('name')->implode(', ') : '');
@endphp

<div class="space-y-5">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="category_id" class="form-label">Kategori <span class="text-red-500">*</span></label>
            <select id="category_id" name="category_id" required class="form-input">
                <option value="">— Pilih kategori —</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected($selectedCategory == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            @error('category_id')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="document_number" class="form-label">Nomor Dokumen</label>
            <input id="document_number" name="document_number" type="text" value="{{ old('document_number', $item->document_number ?? '') }}" class="form-input" placeholder="mis. FSB/JUKNIS/001">
            @error('document_number')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div>
        <label for="title" class="form-label">Judul <span class="text-red-500">*</span></label>
        <input id="title" name="title" type="text" value="{{ old('title', $item->title ?? '') }}" required class="form-input">
        @error('title')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="description" class="form-label">Deskripsi Singkat</label>
        <textarea id="description" name="description" rows="2" class="form-input">{{ old('description', $item->description ?? '') }}</textarea>
        @error('description')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="content" class="form-label">Konten (opsional)</label>
        <textarea id="content" name="content" rows="6" class="form-input">{{ old('content', $item->content ?? '') }}</textarea>
        @error('content')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="tags" class="form-label">Tag</label>
            <input id="tags" name="tags" type="text" value="{{ $tagsValue }}" class="form-input" placeholder="pisahkan dengan koma">
            <p class="mt-1 text-xs text-ink-400">Contoh: cuti, keuangan, onboarding</p>
            @error('tags')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="status" class="form-label">Status <span class="text-red-500">*</span></label>
            <select id="status" name="status" required class="form-input">
                <option value="draft" @selected(old('status', $item->status ?? 'draft') === 'draft')>Draft</option>
                <option value="published" @selected(old('status', $item->status ?? '') === 'published')>Dipublikasikan</option>
            </select>
            @error('status')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    @unless ($isEdit)
        <div class="rounded-xl border border-dashed border-ink-300 bg-ink-50/60 p-4">
            <p class="mb-3 text-sm font-medium text-ink-700">Dokumen Awal (opsional)</p>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="document" class="form-label">File PDF</label>
                    <input id="document" name="document" type="file" accept="application/pdf" class="form-input file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-brand-700">
                    @error('document')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="version" class="form-label">Versi</label>
                    <input id="version" name="version" type="text" value="{{ old('version', '1.0') }}" class="form-input">
                </div>
            </div>
            <div class="mt-4">
                <label for="change_notes" class="form-label">Catatan Perubahan</label>
                <textarea id="change_notes" name="change_notes" rows="2" class="form-input">{{ old('change_notes') }}</textarea>
            </div>
        </div>
    @endunless
</div>