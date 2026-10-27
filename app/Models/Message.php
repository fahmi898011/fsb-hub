<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pesan dalam sebuah percakapan (PRD Chat §4).
 *
 * Urutan waktu (`created_at`) adalah satu-satunya acuan urutan pesan —
 * id dipakai sebagai penanda "pesan terakhir yang sudah dimuat" saat polling,
 * tetapi tampilan tetap memakai waktu agar tidak bergantung pada id.
 *
 * AGENT: asisten | 2026-10-06 | relasi/scope/helper pesan lanjutan + pinned_at | modul Chat §15
 */
class Message extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'body',
        'parent_id',
        'edited_at',
        'pinned_at',
    ];

    protected $casts = [
        'edited_at' => 'datetime',
        'pinned_at' => 'datetime',
    ];

    /* ------------------------------------------------------------------ */
    /* Relasi                                                              */
    /* ------------------------------------------------------------------ */

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }

    /** Induk bila pesan ini adalah balasan (fitur menyusul). */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Balasan-balasan langsung atas pesan ini (fitur utas). */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->oldest();
    }

    /* ------------------------------------------------------------------ */
    /* Scope                                                               */
    /* ------------------------------------------------------------------ */

    /** Pesan yang muncul setelah `last_read_at` milik seorang anggota. */
    public function scopeAfter(Builder $query, ?\DateTimeInterface $moment): Builder
    {
        return $moment
            ? $query->where('created_at', '>', $moment)
            : $query;
    }

    /** Pesan yang bukan kiriman user tertentu (dasar hitungan belum dibaca). */
    public function scopeNotFrom(Builder $query, User $user): Builder
    {
        return $query->where('user_id', '!=', $user->getKey());
    }

    public function scopeNewerThan(Builder $query, int $messageId): Builder
    {
        return $query->where('id', '>', $messageId);
    }

    /** Pesan yang disematkan (dipakai panel pin di atas percakapan). */
    public function scopePinned(Builder $query): Builder
    {
        return $query->whereNotNull('pinned_at')->orderByDesc('pinned_at');
    }

    /** Balasan atas sebuah pesan, dibatasi agar satu utas tidak menarik seluruh riwayat. */
    public function scopeRepliesTo(Builder $query, int $parentId): Builder
    {
        return $query->where('parent_id', $parentId)->oldest();
    }

    /* ------------------------------------------------------------------ */
    /* Turunan tampilan                                                    */
    /* ------------------------------------------------------------------ */

    public function isEdited(): bool
    {
        return $this->edited_at !== null;
    }

    public function isPinned(): bool
    {
        return $this->pinned_at !== null;
    }

    /** Benar bila pesan ditulis oleh user tertentu (dasar izin edit/hapus). */
    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && $this->user_id !== null && (int) $this->user_id === (int) $user->getKey();
    }

    /**
     * Nama-nama yang disebut dengan `@` di dalam badan pesan (PRD §15).
     *
     * Mengembalikan token mentah (tanpa `@`), belum dicocokkan ke pengguna.
     * Pencocokan ke anggota percakapan dilakukan ChatNotifier, karena hanya
     * di sana daftar anggota sudah dimuat — model tidak boleh memicu query
     * anggota sendiri hanya demi menyebut satu nama.
     *
     * Pola sengaja membatasi pada huruf/angka/._- supaya tanda baca di
     * akhir kalimat ("@Budi.") tidak ikut terbaca sebagai bagian nama.
     */
    public function mentionedNames(): array
    {
        $body = (string) $this->body;

        if ($body === '' || ! str_contains($body, '@')) {
            return [];
        }

        preg_match_all('/(?<![\w@])@([\p{L}\p{N}._-]{1,40})/u', $body, $matches);

        // Titik di akhir kalimat ("@Budi.") ikut tertangkap karena titik
        // memang sah di dalam nama ("@a.b"); dibuang di sini, bukan di pola,
        // supaya nama bertitik tetap utuh sementara tanda baca akhir hilang.
        $names = array_map(
            static fn (string $name) => rtrim($name, '.'),
            $matches[1] ?? [],
        );

        return array_values(array_unique(array_filter($names, static fn (string $name) => $name !== '')));
    }
}
