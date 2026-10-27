<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Divisi (departments).
 *
 * Divisi mengatur struktur organisasi, BUKAN hak akses — hak akses diatur
 * oleh Role (spatie). Satu divisi menaungi banyak tim.
 */
class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'code',
        'description',
        'status',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $department) {
            if (empty($department->slug)) {
                $department->slug = Str::slug($department->name);
            }
        });
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'department_id');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class, 'department_id');
    }

    /**
     * KPI yang dimiliki divisi ini (PRD Employee KPI/QA §4).
     *
     * AGENT: asisten | 2026-10-02 | tambah relasi | modul KPI, fase 1 master
     */
    public function kpis(): HasMany
    {
        return $this->hasMany(Kpi::class, 'department_id');
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
