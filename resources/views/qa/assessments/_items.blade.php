{{-- Kelola item penilaian (kelebihan/kekurangan per kategori) (PRD §4). --}}
{{-- AGENT: asisten | 2026-10-08 | buat berkas baru | modul QA Assessment, sub-bagian A --}}

@php
    $isDraft = $qaAssessment->status === 'draft';
    $usedCategoryIds = $qaAssessment->items->pluck('category_id');
    $availableCategories = $categories->whereNotIn('id', $usedCategoryIds);
@endphp

<div class="space-y-4">
    <h2 class="text-base font-semibold text-ink-900">Item Penilaian</h2>

    {{-- Tanpa item, tombol Terbitkan sengaja dimatikan; jelaskan caranya di sini
         supaya QA tidak bingung saat melihat pesan "minimal 1 item".
         AGENT: asisten | 2026-10-20 | jelaskan syarat terbit | modul QA Assessment --}}
    @if ($isDraft && $qaAssessment->items->isEmpty())
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <p class="font-medium">Assessment harus punya minimal 1 item sebelum bisa diterbitkan.</p>
            <p class="mt-1">
                Pilih kategori pada formulir di bawah, isi kelebihan/kekurangan, lalu klik
                <span class="font-semibold">Tambah Item</span>. Tombol Terbitkan aktif setelah ada item.
            </p>
        </div>
    @endif

    @if ($qaAssessment->items->isEmpty())
        <x-empty-state title="Belum ada item"
                       description="Tambahkan kategori yang dinilai beserta kelebihan dan kekurangannya." />
    @else
        <div class="space-y-4">
            @foreach ($qaAssessment->items as $item)
                <div class="card card-body space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold text-ink-900">{{ $item->category?->name ?? 'Kategori dihapus' }}</h3>
                        @if ($isDraft)
                            @can('update', $qaAssessment)
                                <form method="POST" action="{{ route('qa.assessments.items.destroy', [$qaAssessment, $item]) }}"
                                      onsubmit="return confirm('Hapus item ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-medium text-red-600 hover:underline">Hapus</button>
                                </form>
                            @endcan
                        @endif
                    </div>

                    <form method="POST" action="{{ route('qa.assessments.items.update', [$qaAssessment, $item]) }}"
                          class="space-y-3" @unless($isDraft) hidden @endunless>
                        @csrf
                        @method('PUT')

                        <div>
                            <label class="form-label">Kelebihan</label>
                            <textarea name="strengths" rows="2" class="form-input w-full"
                                      @unless($isDraft) readonly @endunless>{{ old("strengths.{$item->id}", $item->strengths) }}</textarea>
                        </div>
                        <div>
                            <label class="form-label">Kekurangan</label>
                            <textarea name="weaknesses" rows="2" class="form-input w-full"
                                      @unless($isDraft) readonly @endunless>{{ old("weaknesses.{$item->id}", $item->weaknesses) }}</textarea>
                        </div>

                        @if ($isDraft)
                            @can('update', $qaAssessment)
                                <div class="flex justify-end">
                                    <button type="submit" class="btn-secondary btn-sm">Simpan Item</button>
                                </div>
                            @endcan
                        @endif
                    </form>
                </div>
            @endforeach
        </div>
    @endif

    @if ($isDraft)
        @can('update', $qaAssessment)
            @if ($availableCategories->isNotEmpty())
                <form method="POST" action="{{ route('qa.assessments.items.store', $qaAssessment) }}"
                      class="card card-body space-y-4">
                    @csrf

                    <div>
                        <label for="category_id" class="form-label">Kategori baru</label>
                        <select id="category_id" name="category_id" class="form-input w-full" required>
                            <option value="">Pilih kategori</option>
                            @foreach ($availableCategories as $category)
                                <option value="{{ $category->id }}" @selected((int) old('category_id') === $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('category_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="strengths" class="form-label">Kelebihan</label>
                            <textarea id="strengths" name="strengths" rows="3" class="form-input w-full">{{ old('strengths') }}</textarea>
                        </div>
                        <div>
                            <label for="weaknesses" class="form-label">Kekurangan</label>
                            <textarea id="weaknesses" name="weaknesses" rows="3" class="form-input w-full">{{ old('weaknesses') }}</textarea>
                        </div>
                    </div>

                    @error('strengths')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    @error('weaknesses')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

                    <div class="flex justify-end">
                        <button type="submit" class="btn-primary">Tambah Item</button>
                    </div>
                </form>
            @else
                <p class="text-sm text-ink-500">
                    Semua kategori aktif sudah dipakai.
                    <a href="{{ route('qa.assessment.categories.create') }}" class="font-medium text-brand-700 hover:underline">Buat kategori baru</a>
                    bila masih ada aspek lain yang perlu dinilai.
                </p>
            @endif
        @endcan
    @endif
</div>
