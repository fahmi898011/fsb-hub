@extends('layouts.app')

{{--
    Daftar ujian milik agent — titik masuk menu "Quiz Saya" sejak Quiz pilihan
    ganda diarsipkan dan digantikan ujian essay (PRD QA Assessment §5).

    Halaman ini menampilkan SEMUA status, bukan hanya yang bisa dikerjakan:
    agent perlu tahu mana yang masih menunggu, mana yang sedang dinilai, dan
    mana yang nilainya sudah terbit.

    AGENT: asisten | 2026-10-16 | buat berkas baru | modul Quiz -> Ujian
--}}

@section('title', 'Ujian Saya')

@section('content')
    @php
        $tabs = [
            '' => 'Semua',
            'assigned' => 'Belum dikerjakan',
            'in_progress' => 'Sedang dikerjakan',
            'submitted' => 'Menunggu dinilai',
            'graded' => 'Sudah dinilai',
            'published' => 'Terbit',
        ];

        // Saklar fitur dibaca di sini (bukan di controller) karena hanya
        // memengaruhi tampilan: tombol "Kerjakan" dan pesan mode kertas.
        $digitalMode = App\Models\Setting::enabled('exam.digital_mode', true);
        $paperMode = App\Models\Setting::enabled('exam.paper_mode', true);
    @endphp

    <div class="mb-4">
        <h1 class="text-xl font-semibold text-ink-900">Ujian Saya</h1>
        <p class="text-sm text-ink-500">
            Ujian essay yang ditugaskan kepada Anda. Hasil muncul setelah QA mempublikasikan penilaian.
        </p>
    </div>

    @if (! $digitalMode && $paperMode)
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            Ujian saat ini dilaksanakan dalam <strong>mode kertas</strong>. Kerjakan lembar jawaban yang dibagikan QA;
            QA akan mengunggah scan-nya dan menandai ujian sudah dikumpulkan. Tidak ada yang perlu Anda klik di sini.
        </div>
    @endif

    <nav class="mb-4 flex flex-wrap gap-2">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('qa.exam.forms.my', array_filter(['status' => $key])) }}"
               class="rounded-lg px-3 py-2 text-sm font-medium {{ $status === $key ? 'bg-brand-600 text-white' : 'bg-white text-ink-600 ring-1 ring-ink-200 hover:bg-ink-50' }}">
                {{ $label }}
            </a>
        @endforeach
    </nav>

    @if ($assignments->isEmpty())
        <x-empty-state title="Belum ada ujian"
                       description="Ujian yang ditugaskan QA akan muncul di halaman ini." />
    @else
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-ink-100 bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Ujian</th>
                            <th class="px-5 py-3 font-semibold">Attempt</th>
                            <th class="px-5 py-3 font-semibold">Ditugaskan</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 font-semibold">Nilai</th>
                            <th class="px-5 py-3 text-right font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach ($assignments as $assignment)
                            @php
                                $published = $assignment->status === 'published';
                                $canTake = $digitalMode && in_array($assignment->status, ['assigned', 'in_progress'], true);
                            @endphp
                            <tr class="hover:bg-ink-50">
                                <td class="px-5 py-3">
                                    <p class="font-medium text-ink-900">{{ $assignment->form?->title ?? 'Ujian' }}</p>
                                    @if ($assignment->form?->description)
                                        <p class="max-w-md truncate text-xs text-ink-500">{{ $assignment->form->description }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-ink-600">ke-{{ $assignment->attempt_ke }}</td>
                                <td class="px-5 py-3 text-ink-600">{{ $assignment->created_at?->format('d M Y') ?? '—' }}</td>
                                <td class="px-5 py-3">
                                    <x-badge :variant="match ($assignment->status) {
                                        'published' => 'green',
                                        'graded' => 'brand',
                                        'submitted', 'in_progress' => 'amber',
                                        default => 'gray',
                                    }">{{ ucfirst(str_replace('_', ' ', $assignment->status)) }}</x-badge>
                                </td>
                                <td class="px-5 py-3 text-ink-600">
                                    {{-- Skor hanya untuk hasil yang sudah terbit: sebelum itu
                                         angka masih bisa berubah dan bukan hak agent melihatnya. --}}
                                    @if ($published && $assignment->percentage !== null)
                                        <span class="font-medium text-ink-900">
                                            {{ rtrim(rtrim(number_format((float) $assignment->percentage, 2, '.', ''), '0'), '.') }}%
                                        </span>
                                        <span class="text-xs text-ink-500">({{ $assignment->total_score ?? 0 }}/{{ $assignment->max_score ?? 0 }})</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    @if ($canTake)
                                        <a href="{{ route('qa.exam.forms.take', $assignment) }}" class="text-brand-700 hover:underline">
                                            {{ $assignment->status === 'in_progress' ? 'Lanjutkan' : 'Kerjakan' }}
                                        </a>
                                    @elseif ($published)
                                        <div class="flex items-center justify-end gap-3">
                                            <a href="{{ route('qa.exam.forms.result', $assignment) }}" class="text-brand-700 hover:underline">Lihat hasil</a>
                                            <a href="{{ route('qa.exam.forms.result.pdf', $assignment) }}" class="text-ink-500 hover:underline">PDF</a>
                                        </div>
                                    @elseif ($assignment->status === 'graded')
                                        <a href="{{ route('qa.exam.forms.result', $assignment) }}" class="text-brand-700 hover:underline">Lihat status</a>
                                    @elseif ($assignment->status === 'submitted')
                                        <span class="text-xs text-ink-500">Menunggu penilaian QA</span>
                                    @else
                                        <span class="text-xs text-ink-500">Menunggu dikerjakan</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3">{{ $assignments->links() }}</div>
        </div>
    @endif
@endsection
