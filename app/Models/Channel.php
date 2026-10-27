<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Channel penilaian QA (PRD Employee KPI/QA §4).
 *
 * AGENT: asisten | 2026-10-02 | buat berkas baru | modul KPI, fase 1 master
 *
 * Channel adalah KONTEKS, bukan KPI: Inbound, Outbound, Chat, dan Email
 * masing-masing menentukan scorecard serta daftar parameter yang berbeda.
 */
class Channel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'status',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $channel) {
            if (empty($channel->slug)) {
                $channel->slug = Str::slug($channel->name);
            }
        });
    }

    public function scorecards(): HasMany
    {
        return $this->hasMany(Scorecard::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
