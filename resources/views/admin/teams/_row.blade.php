{{-- Baris tabel tim (desktop). --}}
<tr class="hover:bg-ink-50">
    <td class="px-5 py-3">
        <p class="font-medium text-ink-800">{{ $team->name }}</p>
        <p class="text-xs text-ink-400">{{ $team->description ?: $team->slug }}</p>
    </td>
    <td class="px-5 py-3 text-ink-600">{{ $team->department?->name ?? '—' }}</td>
    <td class="px-5 py-3 text-ink-600">{{ $team->leader?->name ?? '—' }}</td>
    <td class="px-5 py-3 text-ink-600">{{ $team->memberships_count }}</td>
    <td class="px-5 py-3">
        <x-badge :variant="$team->status === 'active' ? 'green' : 'gray'">
            {{ $team->status === 'active' ? 'Aktif' : 'Nonaktif' }}
        </x-badge>
    </td>
    <td class="px-5 py-3 text-right">
        @can('update', $team)
            <a href="{{ route('admin.teams.edit', $team) }}" class="btn-secondary btn-sm">Edit</a>
        @endcan

        @can('delete', $team)
            <form method="POST" action="{{ route('admin.teams.destroy', $team) }}" class="inline"
                  onsubmit="return confirm('Hapus tim ini?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-ghost btn-sm text-red-600 hover:bg-red-50">Hapus</button>
            </form>
        @endcan
    </td>
</tr>
