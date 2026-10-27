<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketReply;
use App\Services\TicketNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tiket milik karyawan (PRD §42 — Phase 5 IT).
 *
 * Setiap user terautentikasi boleh membuat tiket dan membaca tiket MILIKNYA
 * sendiri. Tiket bersifat pribadi: karyawan lain tidak boleh membacanya.
 * Hanya pemegang `ticket.view` (tim IT & Admin) yang boleh masuk lewat sini
 * untuk membaca tiket orang lain.
 */
class TicketController extends Controller
{
    public function __construct(private readonly TicketNotifier $notifier) {}

    /** Daftar tiket milik user yang sedang login. */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Ticket::class);

        $filters = $request->only(['status']);

        $tickets = Ticket::where('user_id', $request->user()->id)
            ->with('assignee')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('tickets.index', compact('tickets', 'filters'));
    }

    public function create(): View
    {
        $this->authorize('create', Ticket::class);

        return view('tickets.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Ticket::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:5000'],
            'category' => ['required', 'in:hardware,software,network,account,other'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
        ]);

        $data['user_id'] = $request->user()->id;
        $data['status'] = 'open';

        $ticket = Ticket::create($data);

        // Pelaku tidak menerima notifikasi atas aksinya sendiri; yang diberi
        // tahu adalah antrean tim IT + Admin (lihat TicketNotifier).
        $this->notifier->created($ticket, $request->user());

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('success', 'Tiket berhasil dibuat. Tim IT akan menindaklanjuti.');
    }

    public function show(Request $request, Ticket $ticket): View
    {
        $this->authorize('view', $ticket);

        $ticket->load(['user', 'assignee', 'replies.user']);

        return view('tickets.show', [
            'ticket' => $ticket,
            // Catatan internal hanya untuk pemegang `ticket.view` (tim IT/Admin);
            // pemilik tiket tidak boleh tahu keberadaannya.
            'replies' => $ticket->replies
                ->when(! $request->user()->can('ticket.view'), fn ($items) => $items->where('is_internal', false))
                ->values(),
        ]);
    }

    /** Balasan publik dari pemilik tiket ke antrean IT. */
    public function reply(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('reply', $ticket);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $reply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $data['message'],
            'is_internal' => false,
        ]);

        $this->notifier->replied($ticket, $reply, $request->user());

        return back()->with('success', 'Balasan terkirim.');
    }
}