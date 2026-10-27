<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class InformationCategory extends Model
{
    use HasFactory;

    /**
     * Kategori yang isinya ditampilkan sebagai seksi "Pengumuman" di Beranda.
     *
     * Slug ini dulu ditulis sebagai string literal di HomeController, sehingga
     * mengganti/menghapus kategori tidak pernah terdeteksi sampai halaman
     * beranda kehilangan pengumumannya tanpa error.
     */
    public const ANNOUNCEMENT_SLUG = 'announcement';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'color',
        'status',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(InformationItem::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
