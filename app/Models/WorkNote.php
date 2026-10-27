<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Catatan kerja pribadi (PRD Catatan).
 *
 * AGENT: asisten | 2026-10-01 | buat berkas baru | modul Catatan Kerja, fase 1 backend
 *
 * Satu baris = satu catatan milik satu karyawan. Tidak ada cara membagikan
 * catatan pada fase ini, jadi tidak ada kolom akses: pemilik (`user_id`)
 * adalah satu-satunya penentu siapa yang boleh membacanya.
 *
 * Catatan dibaca/ditulis karyawan yang sering berpindah PC. Karena itu
 * penyimpanan harus di server (bukan perangkat) dan tidak boleh ada
 * penyimpanan yang gagal tanpa jejak - lihat `lock_version` pada migration.
 */
class WorkNote extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'body',
        'pinned',
        'last_edited_at',
        'lock_version',
    ];

    protected $casts = [
        'pinned' => 'boolean',
        'last_edited_at' => 'datetime',
        'lock_version' => 'integer',
    ];

    /* ------------------------------------------------------------------ */
    /* Relasi                                                              */
    /* ------------------------------------------------------------------ */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Label catatan (PRD Catatan §14).
     *
     * Urut menurut nama supaya tampilan label tidak berubah-ubah antar
     * pemuatan halaman.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(NoteTag::class, 'note_tag', 'work_note_id', 'note_tag_id')
            ->orderBy('name');
    }

    /* ------------------------------------------------------------------ */
    /* Scope                                                               */
    /* ------------------------------------------------------------------ */

    /**
     * Batasi ke catatan milik satu user.
     *
     * Ini penjaga utama privasi: SELURUH query daftar wajib melewati scope
     * ini, bukan menyaring di view. Menyaring di view berarti catatan orang
     * lain sempat terbaca dari database dan hanya disembunyikan tampilannya.
     */
    public function scopeOwnedBy(Builder $query, User|int $user): Builder
    {
        return $query->where('user_id', $user instanceof User ? $user->getKey() : $user);
    }

    /**
     * Urutan tampil: disematkan lebih dulu, lalu yang terakhir disunting.
     *
     * Memakai `COALESCE(last_edited_at, updated_at)` supaya catatan yang baru
     * dibuat (belum pernah disunting isinya) tidak terlempar ke bawah.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('pinned')
            ->orderByRaw('COALESCE(last_edited_at, updated_at) DESC');
    }

    /* ------------------------------------------------------------------ */
    /* Turunan tampilan                                                    */
    /* ------------------------------------------------------------------ */

    /**
     * Judul untuk ditampilkan: pakai `title` bila diisi, kalau tidak ambil
     * baris pertama isi catatan. Karyawan sering hanya mengetik langsung tanpa
     * memberi judul; menampilkan "Tanpa judul" untuk semua catatan akan membuat
     * daftar tidak berguna.
     */
    public function getDisplayTitleAttribute(): string
    {
        if (filled($this->title)) {
            return $this->title;
        }

        $firstLine = trim(Str::before((string) $this->body, "\n"));

        return $firstLine !== '' ? Str::limit($firstLine, 60) : 'Catatan tanpa judul';
    }

    /** Cuplikan isi untuk daftar (baris baru diratakan jadi satu spasi). */
    public function getExcerptAttribute(): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', (string) $this->body) ?? ''), 90);
    }

    /** Benar bila catatan belum pernah disunting isinya sama sekali. */
    public function isUntouched(): bool
    {
        return $this->last_edited_at === null;
    }

    /** Tandai sudah disunting: perbarui waktu + naikkan versi. */
    public function markEdited(): void
    {
        $this->last_edited_at = now();
        $this->lock_version = (int) $this->lock_version + 1;
    }
}
