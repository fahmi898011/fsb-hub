<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lampiran pesan (PRD Chat §4/§8).
 *
 * Berkas disimpan di disk PRIVAT. Tidak ada URL publik: satu-satunya jalan
 * mengambil berkas adalah rute unduh yang memeriksa keanggotaan percakapan.
 * Alasannya, URL disk `public` dapat dibuka siapa pun yang menebak
 * tautannya — pada percakapan pribadi itu sama dengan membocorkan isi DM.
 */
class MessageAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    /** Ukuran berkas dalam bentuk yang mudah dibaca (mis. "1,2 MB"). */
    public function getHumanSizeAttribute(): string
    {
        $bytes = (int) $this->size;

        return match (true) {
            $bytes >= 1048576 => number_format($bytes / 1048576, 1, ',', '.') . ' MB',
            $bytes >= 1024 => number_format($bytes / 1024, 0, ',', '.') . ' KB',
            default => $bytes . ' B',
        };
    }
}
