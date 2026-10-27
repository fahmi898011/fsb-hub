<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Kategori bank soal (PRD Employee KPI/QA §21).
 *
 * AGENT: asisten | 2026-10-05 | buat berkas baru | modul Quiz, fase 4 quiz
 *
 * Topik soal (SOP, Juknis, Script, FAQ, ...). Dipakai untuk menata bank soal
 * dan untuk pemilihan soal berbasis kategori pada versi quiz (PRD §24).
 */
class QuestionCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id',
        'name',
        'slug',
        'description',
        'sort_order',
        'status',
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

    public function division(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuestionBank::class);
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
