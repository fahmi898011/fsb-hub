<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\ExamAssignment;
use App\Models\InformationItem;
use App\Models\QaSamplingAssessment;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkNote;
use App\Services\CategoryService;
use App\Services\SearchService;
use App\Traits\SanitizesSearchTerm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SearchController extends Controller
{
    use SanitizesSearchTerm;

    /** Di bawah ini, pencarian lintas modul terlalu berisik untuk dijalankan. */
    private const MIN_LENGTH = 3;

    /** Cukup untuk mengenali hasil yang dicari; sisanya lewat halaman /cari. */
    private const PER_MODULE = 5;

    /** Detik cache hasil. Lihat penjelasan di `live()`. */
    private const CACHE_SECONDS = 45;

    public function __construct(
        private readonly SearchService $search,
        private readonly CategoryService $categories,
    ) {}

    public function index(Request $request): View
    {
        $term = (string) $request->input('q', '');
        $filters = [
            'category' => $request->input('category'),
        ];

        $results = $term !== ''
            ? $this->search->search($term, 20, $filters['category'])
            : null;

        $categories = $this->categories->active();

        return view('search.index', compact('term', 'results', 'categories', 'filters'));
    }

    /**
     * Pencarian lintas modul untuk kotak pencarian di topbar (JSON).
     *
     * AGENT: asisten | 2026-10-16 | tambah pencarian lintas modul | modul Pencarian
     *
     * Tiga hal yang menentukan bentuk endpoint ini:
     *
     * 1. HASILNYA BERBEDA PER ORANG. Catatan hanya milik sendiri, chat hanya
     *    percakapan yang diikuti, tiket hanya milik sendiri (kecuali tim IT),
     *    ujian hanya assignment sendiri. Karena itu penyaringan dikerjakan di
     *    QUERY, bukan setelah data diambil — baris orang lain tidak boleh
     *    sempat masuk memori proses, apalagi dikirim ke browser.
     *
     * 2. Cache pendek 45 detik. Debounce di browser sudah menahan ketikan,
     *    tetapi hapus-ketik-cepat tetap bisa memicu beberapa permintaan untuk
     *    kata yang sama. Kunci cache memuat id pengguna: tanpa itu, hasil
     *    pencarian orang lain bisa ikut tersaji.
     *
     * 3. Minimal 3 karakter. Query `LIKE %a%` lintas enam tabel adalah cara
     *    termudah membuat database sibuk tanpa memberi hasil yang berguna.
     */
    public function live(Request $request): JsonResponse
    {
        $term = $this->sanitizeSearchTerm((string) $request->query('q', ''));

        if (mb_strlen($term) < self::MIN_LENGTH) {
            return response()->json([
                'query' => $term,
                'total' => 0,
                'groups' => [],
                'hint' => 'Ketik minimal '.self::MIN_LENGTH.' karakter.',
            ]);
        }

        /** @var User $user */
        $user = $request->user();

        $payload = Cache::remember(
            'search:live:'.$user->getKey().':'.sha1($term),
            now()->addSeconds(self::CACHE_SECONDS),
            fn (): array => $this->collect($user, $term),
        );

        return response()->json($payload);
    }

    /**
     * Kumpulkan hasil dari semua modul yang boleh dilihat pengguna.
     *
     * Modul yang tidak menghasilkan apa pun tidak ikut dikirim: judul grup
     * kosong hanya menambah tinggi dropdown tanpa isi.
     *
     * @return array<string, mixed>
     */
    private function collect(User $user, string $term): array
    {
        $groups = array_values(array_filter([
            $this->searchInformation($term),
            $this->searchNotes($user, $term),
            $this->searchConversations($user, $term),
            $this->searchTickets($user, $term),
            $this->searchExams($user, $term),
            $this->searchSampling($user, $term),
        ]));

        return [
            'query' => $term,
            'total' => array_sum(array_map(fn (array $group) => count($group['items']), $groups)),
            'groups' => $groups,
            'more' => route('search', ['q' => $term]),
        ];
    }

    /**
     * Pusat Informasi — satu-satunya modul yang isinya memang untuk semua orang.
     *
     * @return array<string, mixed>|null
     */
    private function searchInformation(string $term): ?array
    {
        $items = InformationItem::query()
            ->published()
            ->with('category')
            ->where(function ($q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                    ->orWhere('document_number', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            })
            ->orderByDesc('published_at')
            ->limit(self::PER_MODULE)
            ->get();

        if ($items->isEmpty()) {
            return null;
        }

        return [
            'module' => 'Informasi',
            'items' => $items->map(fn (InformationItem $item) => [
                'title' => $item->title,
                'subtitle' => $item->document_number ?: $item->category?->name,
                'url' => route('information.show', $item),
            ])->all(),
        ];
    }

    /**
     * Catatan kerja — selalu milik sendiri. Tidak ada catatan bersama.
     *
     * Halaman catatan tidak punya rute "detail"; catatan dibuka lewat query
     * `?note=` pada daftar. Kata kunci ikut dibawa agar daftar di kiri tetap
     * tersaring seperti saat pengguna mencarinya.
     *
     * @return array<string, mixed>|null
     */
    private function searchNotes(User $user, string $term): ?array
    {
        $items = WorkNote::query()
            ->ownedBy($user)
            ->where(function ($q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                    ->orWhere('body', 'like', "%{$term}%");
            })
            ->orderByDesc('last_edited_at')
            ->limit(self::PER_MODULE)
            ->get();

        if ($items->isEmpty()) {
            return null;
        }

        return [
            'module' => 'Catatan Saya',
            'items' => $items->map(fn (WorkNote $note) => [
                'title' => $note->title ?: 'Catatan tanpa judul',
                'subtitle' => Str::limit(trim(strip_tags((string) $note->body)), 80),
                'url' => route('notes.index', ['q' => $term, 'note' => $note->id]),
            ])->all(),
        ];
    }

    /**
     * Chat — hanya percakapan tempat pengguna menjadi anggota.
     *
     * DM tidak punya nama, jadi tidak ikut dicari lewat nama. Mencari nama
     * lawan bicara akan membocorkan percakapan orang lain yang kebetulan
     * menyebut nama yang sama.
     *
     * @return array<string, mixed>|null
     */
    private function searchConversations(User $user, string $term): ?array
    {
        $items = Conversation::query()
            ->active()
            ->whereNotNull('name')
            ->whereHas('members', fn ($q) => $q->whereKey($user->getKey()))
            ->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            })
            ->limit(self::PER_MODULE)
            ->get();

        if ($items->isEmpty()) {
            return null;
        }

        return [
            'module' => 'Chat',
            'items' => $items->map(fn (Conversation $conversation) => [
                'title' => $conversation->name,
                'subtitle' => $conversation->is_private ? 'Kanal privat' : 'Kanal',
                'url' => route('chat.show', $conversation),
            ])->all(),
        ];
    }

    /**
     * Tiket — milik sendiri, atau semua tiket bagi pemegang `ticket.view`.
     *
     * Aturan ini SAMA dengan TicketPolicy::view; kalau policy berubah, baris
     * ini harus ikut berubah. Disaring lewat izin (bukan nama role) supaya
     * role baru yang diberi izin yang sama otomatis ikut benar.
     *
     * @return array<string, mixed>|null
     */
    private function searchTickets(User $user, string $term): ?array
    {
        $canSeeAll = $user->can('ticket.view');

        $items = Ticket::query()
            ->when(! $canSeeAll, fn ($q) => $q->where('user_id', $user->getKey()))
            ->where(function ($q) use ($term) {
                $q->where('ticket_number', 'like', "%{$term}%")
                    ->orWhere('title', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            })
            ->orderByDesc('id')
            ->limit(self::PER_MODULE)
            ->get();

        if ($items->isEmpty()) {
            return null;
        }

        return [
            'module' => 'Tiket IT',
            'items' => $items->map(fn (Ticket $ticket) => [
                'title' => $ticket->ticket_number.' · '.$ticket->title,
                'subtitle' => ucfirst((string) $ticket->status),
                'url' => route('tickets.show', $ticket),
            ])->all(),
        ];
    }

    /**
     * Ujian (dulu "Quiz") — hanya assignment milik agent yang login.
     *
     * Hasil yang sudah dinilai diarahkan ke halaman hasil; yang belum selesai
     * diarahkan ke daftar "Ujian Saya", sebab rute pengerjaan menolak
     * assignment yang statusnya sudah lewat.
     *
     * @return array<string, mixed>|null
     */
    private function searchExams(User $user, string $term): ?array
    {
        $items = ExamAssignment::query()
            ->with('form')
            ->where('agent_id', $user->getKey())
            ->whereHas('form', fn ($q) => $q->where('title', 'like', "%{$term}%"))
            ->orderByDesc('id')
            ->limit(self::PER_MODULE)
            ->get();

        if ($items->isEmpty()) {
            return null;
        }

        return [
            'module' => 'Ujian',
            'items' => $items->map(fn (ExamAssignment $assignment) => [
                'title' => $assignment->form?->title ?? 'Ujian',
                'subtitle' => ucfirst(str_replace('_', ' ', (string) $assignment->status)),
                'url' => in_array($assignment->status, ['graded', 'published'], true)
                    ? route('qa.exam.forms.result', $assignment)
                    : route('qa.exam.forms.my'),
            ])->all(),
        ];
    }

    /**
     * Penilaian sampling — QA/HR melihat semua, karyawan hanya miliknya.
     *
     * Karyawan juga tidak boleh melihat draft miliknya: draft masih milik QA
     * (aturan yang sama dengan QaSamplingAssessmentPolicy::view).
     *
     * @return array<string, mixed>|null
     */
    private function searchSampling(User $user, string $term): ?array
    {
        if (! $user->can('access-qa-sampling')) {
            return null;
        }

        $seesEverything = $user->can('qa_sampling.view');

        $items = QaSamplingAssessment::query()
            ->when(
                ! $seesEverything,
                fn ($q) => $q->where('employee_id', $user->getKey())->where('status', '!=', 'draft'),
            )
            ->where(function ($q) use ($term) {
                $q->where('employee_name_snapshot', 'like', "%{$term}%")
                    ->orWhere('sample_id', 'like', "%{$term}%")
                    ->orWhere('notes', 'like', "%{$term}%");
            })
            ->orderByDesc('id')
            ->limit(self::PER_MODULE)
            ->get();

        if ($items->isEmpty()) {
            return null;
        }

        return [
            'module' => 'Penilaian QA',
            'items' => $items->map(fn (QaSamplingAssessment $assessment) => [
                'title' => trim(($assessment->employee_name_snapshot ?? '—').' · '.($assessment->period ?? '')),
                'subtitle' => Str::limit(trim(strip_tags((string) $assessment->notes)), 80)
                    ?: ucfirst((string) $assessment->status),
                'url' => route('qa.sampling.show', $assessment),
            ])->all(),
        ];
    }
}
