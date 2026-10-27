{{-- Baris tabel divisi (desktop). Dipisah agar index tetap ringkas. --}}
<tr class="hover:bg-ink-50">
    <td class="px-5 py-3">
        <p class="font-medium text-ink-800">
            {{ $department->name }}
            @if ($department->code)
                <span class="ml-1 text-xs font-normal text-ink-400">({{ $department->code }})</span>
            @endif
        </p>
        <p class="text-xs text-ink-400">{{ $department->description ?: $department->slug }}</p>
    </td>
    <td class="px-5 py-3">
        <x-badge :variant="$department->status === 'active' ? 'green' : 'gray'">
            {{ $department->status === 'active' ? 'Aktif' : 'Nonaktif' }}
        </x-badge>
    </td>
    <td class="px-5 py-3 text-ink-600">{{ $department->users_count }}</td>
    <td class="px-5 py-3 text-ink-600">{{ $department->teams_count }}</td>
    <td class="px-5 py-3 text-right">
        @can('update', $department)
            <a href="{{ route('admin.departments.edit', $department) }}" class="btn-secondary btn-sm">Edit</a>
        @endcan

        @can('delete', $department)
            <form method="POST" action="{{ route('admin.departments.destroy', $department) }}" class="inline"
                  onsubmit="return confirm('Hapus divisi ini?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-ghost btn-sm text-red-600 hover:bg-red-50">Hapus</button>
            </form>
        @endcan
    </td>
</tr>
