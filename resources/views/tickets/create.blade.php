@extends('layouts.app')

@section('title', 'Buat Tiket')

@section('content')
    <div class="mb-5">
        <h1 class="text-xl font-semibold text-ink-900">Buat Tiket</h1>
        <p class="text-sm text-ink-500">Jelaskan kendala Anda sedetail mungkin agar tim IT lebih cepat menolong.</p>
    </div>

    <div class="max-w-3xl">
        <form method="POST" action="{{ route('tickets.store') }}" class="card card-body">
            @csrf

            <div class="space-y-5">
                <div>
                    <label for="title" class="form-label">Judul <span class="text-red-500">*</span></label>
                    <input id="title" name="title" type="text" value="{{ old('title') }}" required maxlength="200"
                           class="form-input" placeholder="mis. Laptop tidak bisa terhubung ke Wi-Fi kantor">
                    @error('title')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="category" class="form-label">Kategori <span class="text-red-500">*</span></label>
                        <select id="category" name="category" required class="form-input">
                            <option value="hardware" @selected(old('category') === 'hardware')>Perangkat Keras</option>
                            <option value="software" @selected(old('category') === 'software')>Perangkat Lunak</option>
                            <option value="network" @selected(old('category') === 'network')>Jaringan</option>
                            <option value="account" @selected(old('category') === 'account')>Akun &amp; Akses</option>
                            <option value="other" @selected(old('category', 'other') === 'other')>Lainnya</option>
                        </select>
                        @error('category')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="priority" class="form-label">Prioritas <span class="text-red-500">*</span></label>
                        <select id="priority" name="priority" required class="form-input">
                            <option value="low" @selected(old('priority') === 'low')>Rendah</option>
                            <option value="medium" @selected(old('priority', 'medium') === 'medium')>Sedang</option>
                            <option value="high" @selected(old('priority') === 'high')>Tinggi</option>
                            <option value="urgent" @selected(old('priority') === 'urgent')>Mendesak</option>
                        </select>
                        <p class="mt-1 text-xs text-ink-400">Pilih "Mendesak" hanya bila pekerjaan Anda benar-benar terhenti.</p>
                        @error('priority')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label for="description" class="form-label">Deskripsi Kendala <span class="text-red-500">*</span></label>
                    <textarea id="description" name="description" rows="6" required maxlength="5000"
                              class="form-input" placeholder="Kapan mulai terjadi, langkah yang sudah dicoba, dan pesan galat bila ada.">{{ old('description') }}</textarea>
                    @error('description')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-6 flex items-center gap-2 border-t border-ink-100 pt-5">
                <button type="submit" class="btn-primary">Kirim Tiket</button>
                <a href="{{ route('tickets.index') }}" class="btn-secondary">Batal</a>
            </div>
        </form>
    </div>
@endsection
