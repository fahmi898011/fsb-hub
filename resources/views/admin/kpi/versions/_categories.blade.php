{{-- Daftar kategori + parameter sebuah versi scorecard. --}}
{{-- AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master --}}
<div class="space-y-4">
    @forelse ($categories as $category)
        <div class="card overflow-hidden">
            <div class="flex items-center justify-between border-b border-ink-100 bg-ink-50/70 px-5 py-3">
                <div>
                    <h2 class="font-semibold text-ink-800">{{ $category->name }}</h2>
                    @if ($category->description)<p class="text-xs text-ink-400">{{ $category->description }}</p>@endif
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-ink-400">{{ $category->parameters->count() }} parameter</span>
                    @if ($isDraft)
                        @can('update', $category)
                            <a href="{{ route('admin.kpi.parameters.create', $category) }}" class="btn-secondary btn-sm">Tambah Parameter</a>
                            <a href="{{ route('admin.kpi.categories.edit', $category) }}" class="btn-ghost btn-sm">Edit</a>
                        @endcan
                    @endif
                </div>
            </div>

            <table class="w-full text-left text-sm">
                <thead class="border-b border-ink-100 text-xs uppercase tracking-wide text-ink-400">
                    <tr>
                        <th class="px-5 py-2 font-medium">Parameter</th>
                        <th class="px-5 py-2 font-medium">Bobot</th>
                        <th class="px-5 py-2 font-medium">Tipe</th>
                        <th class="px-5 py-2 font-medium">Jawaban</th>
                        <th class="px-5 py-2 font-medium text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @forelse ($category->parameters as $parameter)
                        <tr class="hover:bg-ink-50">
                            <td class="px-5 py-2.5">
                                <p class="text-ink-800">{{ $parameter->name }}</p>
                                @if ($parameter->guideline)
                                    <p class="text-xs text-ink-400">{{ Str::limit($parameter->guideline, 90) }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-2.5 font-medium text-ink-700">{{ number_format((float) $parameter->weight, 2) }}%</td>
                            <td class="px-5 py-2.5">
                                @php
                                    $typeMap = ['normal' => ['gray', 'Normal'], 'critical' => ['amber', 'Critical'], 'zero_tolerance' => ['red', 'Zero Tolerance']];
                                    [$tv, $tl] = $typeMap[$parameter->parameter_type] ?? ['gray', $parameter->parameter_type];
                                @endphp
                                <x-badge :variant="$tv">{{ $tl }}</x-badge>
                            </td>
                            <td class="px-5 py-2.5 text-xs text-ink-500">
                                {{ $parameter->evaluation_type === 'yes_no_na' ? 'YES / NO / NA' : 'YES / NO' }}
                                @if ($parameter->answer_polarity === 'inverted')
                                    <span class="text-amber-600">&middot; terbalik (YES = penalty)</span>
                                @endif
                            </td>
                            <td class="px-5 py-2.5 text-right">
                                @if ($isDraft)
                                    @can('update', $parameter)
                                        <a href="{{ route('admin.kpi.parameters.edit', $parameter) }}" class="btn-ghost btn-sm">Edit</a>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-4 text-center text-ink-400">Belum ada parameter pada kategori ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @empty
        <x-empty-state title="Versi masih kosong" description="Tambahkan kategori lalu parameter untuk menyusun scorecard ini." />
    @endforelse
</div>
