<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Label catatan kerja (PRD Catatan §14).
 *
 * AGENT: asisten | 2026-10-06 | buat berkas baru | modul Catatan, lanjutan
 *
 * Label selalu milik SATU pengguna. Karena itu setiap query label wajib
 * melewati scope `ownedBy` — sama seperti `WorkNote` — supaya label orang lain
 * tidak pernah ikut tersaring maupun tertaut.
 */
class NoteTag extends Model
{
    protected $fillable = [
        'user_id',
        'name',
    ];

    /* ------------------------------------------------------------------ */
    /* Relasi                                                              */
    /* ------------------------------------------------------------------ */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notes(): BelongsToMany
    {
        return $this->belongsToMany(WorkNote::class, 'note_tag', 'note_tag_id', 'work_note_id');
    }

    /* ------------------------------------------------------------------ */
    /* Scope                                                               */
    /* ------------------------------------------------------------------ */

    /** Batasi ke label milik satu pengguna (penjaga privasi utama). */
    public function scopeOwnedBy(Builder $query, User|int $user): Builder
    {
        return $query->where('user_id', $user instanceof User ? $user->getKey() : $user);
    }

    /** Urut menurut nama supaya daftar label stabil. */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('name');
    }
}
