<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class InformationItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'title',
        'slug',
        'document_number',
        'description',
        'content',
        'status',
        'current_version_id',
        'published_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $item) {
            if (empty($item->slug)) {
                $item->slug = Str::slug($item->title);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InformationCategory::class, 'category_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(InformationVersion::class, 'information_id')->latest();
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(InformationVersion::class, 'current_version_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'information_tag', 'information_id', 'tag_id');
    }

    public function views(): HasMany
    {
        return $this->hasMany(DocumentView::class, 'information_id');
    }

    public function downloads(): HasMany
    {
        return $this->hasMany(Download::class, 'information_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeLatestPublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->orderByDesc('published_at');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getDocumentFileNameAttribute(): ?string
    {
        $version = $this->currentVersion;

        if (! $version || ! $version->file_name) {
            return null;
        }

        $base = Str::slug($this->title, '_');

        return $base . '_v' . $version->version . '.' . pathinfo($version->file_name, PATHINFO_EXTENSION);
    }
}
