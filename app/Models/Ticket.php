<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_number',
        'user_id',
        'assigned_to',
        'title',
        'description',
        'category',
        'priority',
        'status',
        'resolved_at',
        'closed_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $ticket) {
            if (empty($ticket->ticket_number)) {
                $ticket->ticket_number = 'TIK-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class)->oldest();
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'open' => 'Terbuka',
            'in_progress' => 'Diproses',
            'resolved' => 'Selesai',
            'closed' => 'Ditutup',
            default => 'Tidak diketahui',
        };
    }

    public function getPriorityLabelAttribute(): string
    {
        return match ($this->priority) {
            'low' => 'Rendah',
            'medium' => 'Sedang',
            'high' => 'Tinggi',
            'urgent' => 'Mendesak',
            default => 'Sedang',
        };
    }

    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category) {
            'hardware' => 'Perangkat Keras',
            'software' => 'Perangkat Lunak',
            'network' => 'Jaringan',
            'account' => 'Akun & Akses',
            'other' => 'Lainnya',
            default => 'Lainnya',
        };
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['open', 'in_progress']);
    }

    public function getRouteKeyName(): string
    {
        return 'ticket_number';
    }
}