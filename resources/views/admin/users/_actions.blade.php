@unless ($user->is(auth()->user()))
    <form method="POST" action="{{ route('admin.users.status', $user) }}">
        @csrf
        <button type="submit" class="btn-secondary btn-sm">
            {{ $user->isActive() ? 'Nonaktifkan' : 'Aktifkan' }}
        </button>
    </form>

    <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
          onsubmit="return confirm('Hapus akun {{ $user->name }}? Tindakan ini tidak dapat dibatalkan.');">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn-danger btn-sm">Hapus</button>
    </form>
@endunless
