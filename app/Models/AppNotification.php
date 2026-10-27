<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    protected $table = 'app_notifications';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'url',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function markAsRead(): void
    {
        if (! $this->read_at) {
            $this->update(['read_at' => now()]);
        }
    }

    public function getIconAttribute(): string
    {
        return match ($this->type) {
            'information' => 'document-text',
            'ticket' => 'wrench',
            'announcement' => 'megaphone',
            'chat' => 'chat',
            'qa_sampling' => 'clipboard-check',
            'qa_assessment' => 'clipboard-document-check',
            'qa_exam' => 'academic-cap',
            default => 'bell',
        };
    }
}