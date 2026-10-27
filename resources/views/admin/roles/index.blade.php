@extends('layouts.app')

@section('title', 'Role & Permission')

@section('content')
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-ink-900">Role & Permission</h1>
            <p class="text-sm text-ink-500">Atur hak akses tiap role. Perubahan berlaku langsung setelah disimpan.</p>
        </div>

        <a href="{{ route('admin.roles.create') }}" class="btn-primary shrink-0">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
            </svg>
            Tambah Role
        </a>
    </div>

    <div class="card overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-ink-100 bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                <tr>
                    <th class="px-5 py-3 font-semibold">Role</th>
                    <th class="px-5 py-3 font-semibold">Jenis</th>
                    <th class="px-5 py-3 font-semibold">Jumlah Permission</th>
                    <th class="px-5 py-3 font-semibold">Jumlah Pengguna</th>
                    <th class="px-5 py-3 text-right font-semibold">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-ink-100">
                @foreach ($roles as $role)
                    @php($isFunctional = in_array($role->name, $functionalRoles, true))

                    <tr class="hover:bg-ink-50">
                        <td class="px-5 py-3">
                            <p class="font-medium text-ink-900">{{ \App\Models\User::roleLabelFor($role->name) }}</p>
                            <p class="text-xs text-ink-400">{{ $role->name }}</p>
                        </td>
                        <td class="px-5 py-3">
                            @if ($isFunctional)
                                <x-badge variant="brand">Bawaan</x-badge>
                            @else
                                <x-badge variant="gray">Kustom</x-badge>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-ink-600">{{ $role->permissions_count }}</td>
                        <td class="px-5 py-3 text-ink-600">{{ $role->users_count }}</td>
                        <td class="px-5 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.roles.edit', $role) }}" class="btn-secondary btn-sm">Atur</a>

                                @unless ($isFunctional)
                                    <form method="POST" action="{{ route('admin.roles.destroy', $role) }}"
                                          onsubmit="return confirm('Hapus role {{ $role->name }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-danger btn-sm">Hapus</button>
                                    </form>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="mt-4 text-xs text-ink-500">
        Role <span class="font-medium">Bawaan</span> tidak dapat dihapus karena dipakai sistem.
        Role yang masih dipakai pengguna juga tidak dapat dihapus.
    </p>
@endsection
