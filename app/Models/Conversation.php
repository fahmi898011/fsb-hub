<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Percakapan (PRD Chat §4) — satu tabel untuk dua tipe.
 *
 * `direct`  = DM 1-lawan-1: tepat dua anggota, tanpa nama (judul diturunkan
 *             dari nama lawan bicara).
 * `channel` = grup: punya nama, publik atau privat, keanggotaan manual.
 *
 * Keputusan penting: TIDAK ada relasi ke divisi. PRD §6/§11 menegaskan
 * keanggotaan kanal bersifat manual — divisi hanya acuan saat admin/qa
 * menyusun anggota, sehingga tidak ada sinkronisasi otomatis.
 *
 * Dua kolom tambahan dijaga oleh `ChatService`, bukan oleh model:
 * - `last_message_at` (rencana B2): waktu pesan terakhir, untuk mengurutkan
 *   daftar percakapan tanpa subquery per baris.
 * - `direct_pair` (rencana B4): pasangan id anggota DM dalam urutan menaik,
 *   sebagai penanda unik anti DM ganda.
 */
class Conversation extends Model
{
    use HasFactory;

    public const TYPE_DIRECT = 'direct';

    public const TYPE_CHANNEL = 'channel';

    protected $fillable = [
        'type',
        'name',
        'description',
        'is_private',
        'archived_at',
        'created_by',
        'last_message_at',
        'direct_pair',
    ];

    protected $casts = [
        'is_private' => 'boolean',
        'archived_at' => 'datetime',
        'last_message_at' => 'datetime',
    ];

    /* ------------------------------------------------------------------ */
    /* Relasi                                                              */
    /* ------------------------------------------------------------------ */

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_members')
            ->withPivot(['last_read_at', 'joined_at'])
            ->withTimestamps();
    }

    /** Keanggotaan sebagai model, dipakai saat perlu mengubah `last_read_at`. */
    public function memberships(): HasMany
    {
        return $this->hasMany(ConversationMember::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->oldest();
    }

    /** Pesan terakhir, dipakai sebagai ringkasan pada daftar percakapan. */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* ------------------------------------------------------------------ */
    /* Scope                                                               */
    /* ------------------------------------------------------------------ */

    /** Kanal yang diarsipkan tidak muncul di daftar percakapan aktif. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function scopeDirect(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_DIRECT);
    }

    public function scopeChannel(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_CHANNEL);
    }

    /**
     * Percakapan yang diikuti user.
     *
     * Inilah penjaga privasi utama: percakapan yang tidak diikuti TIDAK PERNAH
     * masuk daftar, sehingga admin pun tidak melihat DM orang lain (PRD §7).
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->whereHas('members', fn ($q) => $q->whereKey($user->getKey()));
    }

    /* ------------------------------------------------------------------ */
    /* Helper                                                              */
    /* ------------------------------------------------------------------ */

    public function isDirect(): bool
    {
        return $this->type === self::TYPE_DIRECT;
    }

    public function isChannel(): bool
    {
        return $this->type === self::TYPE_CHANNEL;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /** Keanggotaan bersumber dari SATU tempat: tabel pivot. */
    public function hasMember(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->relationLoaded('members')) {
            return $this->members->contains(fn (User $member) => $member->is($user));
        }

        return $this->members()->whereKey($user->getKey())->exists();
    }

    /** Lawan bicara pada percakapan `direct` (null bila bukan peserta). */
    public function otherParticipant(User $user): ?User
    {
        if (! $this->isDirect()) {
            return null;
        }

        $members = $this->relationLoaded('members') ? $this->members : $this->members()->get();

        return $members->first(fn (User $member) => ! $member->is($user));
    }

    /**
     * Judul percakapan dari sudut pandang seorang anggota.
     *
     * DM tidak menyimpan nama: judulnya selalu nama lawan bicara. Ini
     * membuat DM tetap benar walaupun nama diubah belakangan, dan mencegah
     * judul "Budi" tersimpan selamanya padahal Budi sudah berganti nama.
     */
    public function titleFor(User $user): string
    {
        if ($this->isDirect()) {
            return $this->otherParticipant($user)?->name ?? 'Percakapan pribadi';
        }

        return $this->name ?: 'Kanal tanpa nama';
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    /* ------------------------------------------------------------------ */
    /* Penanda pasangan DM (rencana B4)                                    */
    /* ------------------------------------------------------------------ */

    /**
     * Penanda pasangan DM: kedua id anggota dalam urutan menaik ("12:34").
     *
     * Urutan tetap inilah yang membuat pasangan (A,B) dan (B,A) menghasilkan
     * nilai yang SAMA — prinsip yang sama dengan pemeriksaan `whereHas` ganda
     * di service, hanya saja sekarang ada INDEKS UNIK yang menjaganya.
     *
     * Mengembalikan null untuk kanal atau DM yang anggotanya belum lengkap.
     */
    public static function directPair(int $userA, int $userB): ?string
    {
        if ($userA === $userB) {
            return null;
        }

        return min($userA, $userB) . ':' . max($userA, $userB);
    }

    /**
     * `ChatService` memanggil ini saat membuat DM. Ditaruh di model karena
     * nilainya murni turunan dari data percakapan, dan service hanya perlu
     * tahu bahwa setiap DM memilikinya.
     *
     * @param  iterable<User|int>  $members  anggota DM (harus tepat dua)
     */
    public static function directPairFor(iterable $members): ?string
    {
        $ids = collect($members)
            ->map(fn ($member) => $member instanceof User ? $member->getKey() : (int) $member)
            ->unique()
            ->values()
            ->all();

        if (count($ids) !== 2) {
            return null;
        }

        return self::directPair($ids[0], $ids[1]);
    }
}
