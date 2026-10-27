<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use App\Services\ChatService;
use App\Traits\SanitizesSearchTerm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Daftar karyawan & pintu masuk DM (PRD Chat §5 fitur #3/§7).
 *
 * Halaman ini BUKAN fitur tambahan: tanpa daftar karyawan, DM tidak dapat
 * dimulai dari mana pun karena daftar percakapan hanya menampilkan percakapan
 * yang sudah ada.
 *
 * Menampilkan nama, jabatan/role, dan divisi adalah hal yang wajar untuk buku
 * telepon internal; yang TIDAK ditampilkan adalah email/telepon, karena
 * halaman ini hanya diperlukan untuk memulai percakapan.
 */
class DirectoryController extends Controller
{
    use SanitizesSearchTerm;

    public function __construct(private readonly ChatService $chat) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Conversation::class);

        $term = $this->sanitizeSearchTerm($request->query('search'));

        $users = User::query()
            ->where('status', 'active')
            ->whereKeyNot($request->user()->getKey())
            ->with('division')
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', "%{$term}%")
                        ->orWhere('employee_id', 'like', "%{$term}%")
                        ->orWhereHas('division', fn ($d) => $d->where('name', 'like', "%{$term}%"));
                });
            })
            ->orderBy('name')
            ->paginate(24)
            ->withQueryString();

        // DM yang sudah ada ditandai agar tombol berbunyi "Buka Percakapan"
        // (bukan "Mulai Chat") dan tidak membuat percakapan baru saat diklik.
        $existing = Conversation::query()
            ->direct()
            ->forUser($request->user())
            ->with('members')
            ->get();

        $directByUser = [];

        foreach ($existing as $conversation) {
            $other = $conversation->otherParticipant($request->user());

            if ($other) {
                $directByUser[$other->getKey()] = $conversation->getKey();
            }
        }

        return view('chat.directory', compact('users', 'term', 'directByUser'));
    }

    /**
     * Mulai (atau buka) DM dengan seseorang, lalu arahkan ke percakapannya.
     *
     * Pola "POST lalu redirect" dipakai — bukan tautan GET yang langsung
     * membuat percakapan — supaya crawler/prefetch tidak tanpa sengaja
     * membuat percakapan baru.
     */
    public function store(Request $request, User $user): RedirectResponse
    {
        $this->authorize('startDirect', [Conversation::class, $user]);

        $conversation = $this->chat->findOrCreateDirect($request->user(), $user);

        return redirect()->route('chat.show', $conversation);
    }
}
