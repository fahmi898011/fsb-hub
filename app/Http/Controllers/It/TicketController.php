<?php

namespace App\Http\Controllers\It;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Services\TicketNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Antrean tiket bersama (PRD §42 — Phase 5 IT).
 *
 * Hanya untuk pemegang `ticket.view` (role `it` dan `admin`). Di sini tiket
 * tampil utuh: termasuk catatan internal yang tidak boleh dilihat pemilik
 * tiket. Otorisasi dipegang TicketPolicy supaya aturannya satu sumber.
 */
class TicketController extends Controller
{
    public function __construct(private readonly TicketNotifier $notifier) {}

    public function dashboard(): View
    {
        $this->authorize('viewAny', Ticket::class);

        $stats = [
            'open' => Ticket::where('status', 'open')->count(),
            'in_progress' => Ticket::where('status', 'in_progress')->count(),
            'resolved' => Ticket::where('status', 'resolved')->count(),
            'closed' => Ticket::where('status', 'closed')->count(),
            'total' => Ticket::count(),
            'unassigned' => Ticket::open()->whereNull('assigned_to')->count(),
            'urgent' => Ticket::open()->where('priority', 'urgent')->count(),
            'mine' => Ticket::open()->where('assigned_to', auth()->id())->count(),
            // `resolved_at` dipertahankan saat tiket naik ke `closed`, jadi
            // penyelesaian minggu ini tetap terhitung walau tiketnya sudah
            // ditutup — kalau memakai status saja, angka ini diam-diam turun.
            'resolved_7' => Ticket::whereNotNull('resolved_at')
                ->where('resolved_at', '>=', now()->subDays(7))
                ->count(),
            'oldest_unassigned_days' => $this->daysSince(
                Ticket::open()->whereNull('assigned_to')->min('created_at')
            ),
        ];

        // Antrean kerja: tiket yang belum selesai, mendesak lebih dulu, lalu
        // yang paling lama menunggu. Tiket selesai/ditutup bukan pekerjaan
        // yang menunggu, jadi tidak ikut ditampilkan di sini.
        $queue = Ticket::with(['user', 'assignee'])
            ->open()
            ->orderByRaw("case priority when 'urgent' then 1 when 'high' then 2 when 'medium' then 3 else 4 end")
            ->oldest()
            ->limit(6)
            ->get();

        return view('it.dashboard', compact('stats', 'queue'));
    }

    /** Umur data dalam hari; null bila tidak ada data. */
    private function daysSince(?string $timestamp): ?int
    {
        if (! $timestamp) {
            return null;
        }

        return (int) floor(abs(now()->diffInDays($timestamp)));
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Ticket::class);

        $filters = $request->only(['status', 'priority', 'category', 'search', 'assigned']);

        // `active` adalah pintasan "belum selesai" (terbuka + diproses) supaya
        // tautan dari kartu dashboard tidak perlu memilih salah satu status.
        $status = $filters['status'] ?? null;

        $tickets = Ticket::with(['user', 'assignee'])
            ->when($status === 'active', fn ($query) => $query->open())
            ->when($status && $status !== 'active', fn ($query) => $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn ($q, $p) => $q->where('priority', $p))
            ->when($filters['category'] ?? null, fn ($q, $c) => $q->where('category', $c))
            ->when(($filters['assigned'] ?? null) === 'me', fn ($q) => $q->where('assigned_to', auth()->id()))
            ->when(($filters['assigned'] ?? null) === 'none', fn ($q) => $q->whereNull('assigned_to'))
            ->when($filters['search'] ?? null, function ($q, $term) {
                $q->where(function ($sub) use ($term) {
                    $sub->where('title', 'like', "%{$term}%")
                        ->orWhere('ticket_number', 'like', "%{$term}%");
                });
            })
            ->orderByRaw("case priority when 'urgent' then 1 when 'high' then 2 when 'medium' then 3 else 4 end")
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('it.tickets.index', compact('tickets', 'filters'));
    }

    public function show(Ticket $ticket): View
    {
        $this->authorize('view', $ticket);

        $ticket->load(['user', 'assignee', 'replies.user']);

        $agents = User::role(['it', 'admin'])->orderBy('name')->get();

        return view('it.tickets.show', [
            'ticket' => $ticket,
            // Tim IT melihat percakapan utuh, termasuk catatan internal.
            'replies' => $ticket->replies->values(),
            'agents' => $agents,
        ]);
    }

    public function assign(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('assign', $ticket);

        $data = $request->validate([
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        $wasAssignedTo = $ticket->assigned_to;

        $ticket->update([
            'assigned_to' => $data['assigned_to'],
            'status' => $data['assigned_to'] && $ticket->status === 'open' ? 'in_progress' : $ticket->status,
        ]);

        // Hanya beri tahu bila penanggung jawabnya benar-benar berubah,
        // supaya mengirim ulang form tidak memicu notifikasi ganda.
        if ($ticket->assigned_to && $ticket->assigned_to !== $wasAssignedTo) {
            $this->notifier->assigned($ticket, $request->user());
        }

        return back()->with('success', 'Penugasan diperbarui.');
    }

    public function updateStatus(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);

        $data = $request->validate([
            'status' => ['required', 'in:open,in_progress,resolved,closed'],
        ]);

        $before = $ticket->status;

        $updates = ['status' => $data['status']];

        // Stempel waktu diisi saat status pertama kali berpindah, dipertahankan
        // saat tiket naik ke `closed`, dan dibersihkan bila tiket dibuka
        // kembali agar tidak menyesatkan.
        $resolvedAt = $ticket->resolved_at;

        if ($data['status'] === 'resolved' || $data['status'] === 'closed') {
            $resolvedAt ??= now();
        } else {
            $resolvedAt = null;
        }

        $updates['resolved_at'] = $resolvedAt;
        $updates['closed_at'] = $data['status'] === 'closed'
            ? ($ticket->closed_at ?? now())
            : null;

        $ticket->update($updates);

        if ($before !== $data['status']) {
            $this->notifier->statusChanged($ticket->fresh(), $request->user());
        }

        return back()->with('success', 'Status tiket diperbarui.');
    }

    public function reply(Request $request, Ticket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'is_internal' => ['nullable', 'boolean'],
        ]);

        $isInternal = (bool) ($data['is_internal'] ?? false);

        $this->authorize($isInternal ? 'writeInternalNote' : 'reply', $ticket);

        $reply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $data['message'],
            'is_internal' => $isInternal,
        ]);

        $this->notifier->replied($ticket, $reply, $request->user());

        return back()->with('success', $isInternal ? 'Catatan internal ditambahkan.' : 'Balasan terkirim.');
    }
}
