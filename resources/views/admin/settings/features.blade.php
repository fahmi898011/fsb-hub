@extends('layouts.app')

{{-- Pengaturan Fitur: saklar on/off tanpa deploy. --}}
{{-- AGENT: asisten | 2026-10-16 | buat berkas baru | modul Pengaturan Fitur --}}

@section('title', 'Pengaturan Fitur')

@section('content')
    <div class="mb-5">
        <h1 class="text-xl font-semibold text-ink-900">Pengaturan Fitur</h1>
        <p class="text-sm text-ink-500">
            Nyalakan atau matikan perilaku modul tanpa mengubah kode. Perubahan berlaku
            langsung untuk seluruh pengguna dan tercatat di Audit Log.
        </p>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.features.update') }}" class="space-y-5">
        @csrf
        @method('PUT')

        @forelse ($settings->groupBy('group') as $group => $items)
            <div class="card overflow-hidden">
                <div class="border-b border-ink-100 px-5 py-3">
                    <h2 class="text-sm font-semibold text-ink-700">{{ $items->first()->group_label }}</h2>
                </div>

                <div class="divide-y divide-ink-100">
                    @foreach ($items as $setting)
                        <label for="feature-{{ $setting->key }}"
                               class="flex cursor-pointer items-start justify-between gap-4 px-5 py-4 hover:bg-ink-50">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-ink-900">{{ $setting->label }}</p>
                                <p class="mt-0.5 text-xs text-ink-500">{{ $setting->description }}</p>
                                <p class="mt-1 font-mono text-[11px] text-ink-400">{{ $setting->key }}</p>
                            </div>

                            <div class="flex shrink-0 items-center gap-2 pt-0.5">
                                {{-- Hidden 0 lebih dulu: checkbox yang tidak dicentang
                                     tidak dikirim browser, sehingga tanpa ini saklar
                                     tidak akan pernah bisa dimatikan. --}}
                                <input type="hidden" name="settings[{{ $setting->key }}]" value="0">
                                <input id="feature-{{ $setting->key }}"
                                       type="checkbox"
                                       name="settings[{{ $setting->key }}]"
                                       value="1"
                                       @checked($setting->isOn())
                                       class="h-5 w-5 rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="card card-body text-sm text-ink-500">
                Belum ada saklar fitur. Jalankan <code class="font-mono">php artisan migrate</code>.
            </div>
        @endforelse

        @if ($settings->isNotEmpty())
            <div class="flex justify-end">
                <button type="submit" class="btn-primary">Simpan Pengaturan</button>
            </div>
        @endif
    </form>
@endsection
