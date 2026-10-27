<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Keanggotaan percakapan (PRD Chat §4).
 *
 * Dibuat sebagai model (bukan sekadar pivot) karena `last_read_at` sering
 * dibaca dan ditulis sendiri: saat percakapan dibuka, saat polling pesan,
 * dan saat memutuskan siapa yang masih perlu diberi notifikasi.
 */
class ConversationMember extends Pivot
{
    protected $table = 'conversation_members';

    public $incrementing = true;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'last_read_at',
        'joined_at',
    ];

    protected $casts = [
        'last_read_at' => 'datetime',
        'joined_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
