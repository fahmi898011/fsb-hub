@extends('layouts.app')

@section('title', 'Impor Karyawan')

@section('content')
    <div class="mb-5">
        <a href="{{ route('admin.users.index') }}" class="text-sm text-ink-500 hover:text-ink-800">&larr; Kembali ke Manajemen Pengguna</a>
        <h1 class="mt-1 text-xl font-semibold text-ink-900">Impor Karyawan dari Excel</h1>
        <p class="text-sm text-ink-500">Unggah satu berkas berisi banyak karyawan sekaligus, tanpa input satu per satu.</p>
    </div>

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3">
            @foreach ($errors->all() as $error)
                <p class="text-sm text-red-800">{{ $error }}</p>
            @endforeach
        </div>
    @endif

    {{-- Langkah 1: unduh template --}}
    <div class="card card-body mb-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="max-w-2xl">
                <h2 class="text-base font-semibold text-ink-900">1. Unduh template</h2>
                <p class="mt-1 text-sm text-ink-600">
                    Gunakan template resmi agar formatnya sesuai. Kolom wajib:
                    <span class="font-medium text-ink-800">nama</span>,
                    <span class="font-medium text-ink-800">email</span>, dan
                    <span class="font-medium text-ink-800">role</span>.
                    Kolom lain opsional. Urutan kolom bebas.
                </p>
                <p class="mt-2 text-xs text-ink-500">
                    Role yang dikenali: admin, qa, employee, supervisor, project_leader, it, hr.
                    Kolom <span class="font-medium">password</span> dikosongkan = memakai
                    <span class="font-medium">{{ \App\Services\EmployeeImportService::DEFAULT_PASSWORD }}</span>.
                </p>
            </div>

            <a href="{{ route('admin.users.import.template') }}" class="btn-secondary shrink-0">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M4 19h16" />
                </svg>
                Unduh Template
            </a>
        </div>
    </div>

    {{-- Langkah 2: unggah --}}
    <div class="card card-body mb-5">
        <h2 class="text-base font-semibold text-ink-900">2. Unggah berkas</h2>
        <p class="mt-1 text-sm text-ink-600">Format .xlsx, .xls, atau .csv. Maksimal 5 MB.</p>

        <form method="POST" action="{{ route('admin.users.import.preview') }}" enctype="multipart/form-data" class="mt-4">
            @csrf
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label for="file" class="form-label">Berkas karyawan</label>
                    <input id="file" name="file" type="file" accept=".xlsx,.xls,.csv" required
                           class="form-input file:mr-3 file:rounded-lg file:border-0 file:bg-ink-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-ink-700">
                </div>

                <button type="submit" class="btn-primary shrink-0">
                    Periksa Berkas
                </button>
            </div>
        </form>
    </div>

    @isset($result)
        {{-- Langkah 3: pratinjau --}}
        <div class="card mb-5">
            <div class="card-header">
                <div>
                    <h2 class="text-base font-semibold text-ink-900">3. Pratinjau hasil pemeriksaan</h2>
                    <p class="text-sm text-ink-500">Berkas: {{ $filename }}</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <x-badge variant="green">{{ $result['valid_count'] }} valid</x-badge>
                    @if ($result['invalid_count'] > 0)
                        <x-badge variant="red">{{ $result['invalid_count'] }} ditolak</x-badge>
                    @endif
                    <x-badge variant="gray">{{ $result['total'] }} baris</x-badge>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-ink-200 bg-ink-50 text-xs uppercase tracking-wide text-ink-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Baris</th>
                            <th class="px-5 py-3 font-semibold">Nama</th>
                            <th class="px-5 py-3 font-semibold">Email</th>
                            <th class="px-5 py-3 font-semibold">NIP</th>
                            <th class="px-5 py-3 font-semibold">Departemen</th>
                            <th class="px-5 py-3 font-semibold">Role</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 font-semibold">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach ($result['rows'] as $row)
                            <tr class="{{ $row['status'] === 'invalid' ? 'bg-red-50/60' : '' }}">
                                <td class="px-5 py-3 text-ink-500">{{ $row['line'] }}</td>
                                <td class="px-5 py-3 font-medium text-ink-900">{{ $row['data']['nama'] ?: '—' }}</td>
                                <td class="px-5 py-3 text-ink-600">{{ $row['data']['email'] ?: '—' }}</td>
                                <td class="px-5 py-3 text-ink-600">{{ $row['data']['nip'] ?: '—' }}</td>
                                <td class="px-5 py-3 text-ink-600">{{ $row['data']['departemen'] ?: '—' }}</td>
                                <td class="px-5 py-3 text-ink-600">{{ $row['data']['role'] ?: '—' }}</td>
                                <td class="px-5 py-3">
                                    @if ($row['status'] === 'valid')
                                        <x-badge variant="green">OK</x-badge>
                                    @else
                                        <x-badge variant="red">Ditolak</x-badge>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-ink-600">
                                    @forelse ($row['errors'] as $error)
                                        <p class="text-red-700">{{ $error }}</p>
                                    @empty
                                        <span class="text-ink-400">—</span>
                                    @endforelse
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Langkah 4: simpan --}}
        @if ($result['invalid_count'] > 0)
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                <p class="text-sm text-amber-900">
                    Ada {{ $result['invalid_count'] }} baris yang tidak valid. Perbaiki berkas lalu unggah ulang.
                    Impor hanya berjalan bila seluruh baris valid, supaya tidak ada data yang masuk setengah.
                </p>
            </div>
        @else
            <form method="POST" action="{{ route('admin.users.import.store') }}">
                @csrf
                <button type="submit" class="btn-primary">
                    Simpan {{ $result['valid_count'] }} Karyawan
                </button>
            </form>
        @endif
    @endisset
@endsection
