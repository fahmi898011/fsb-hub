<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Tim kerja (teams).
 *
 * Tim dipimpin seorang leader dan bernaung di bawah satu divisi. Anggota
 * dicatat di `team_members` (relasi many-to-many ke User).
 */
class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id',
        'name',
        'slug',
        'description',
        'leader_id',
        'status',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $team) {
            if (empty($team->slug)) {
                $team->slug = Str::slug($team->name);
            }
        });
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /** Pemimpin tim (biasanya role `supervisor` / `project_leader`). */
    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members', 'team_id', 'user_id')
            ->withPivot('role')
            ->withTimestamps()
            ->orderBy('name');
    }

    /** Baris pivot, berguna saat perlu menampilkan peran leader/member. */
    public function memberships(): HasMany
    {
        return $this->hasMany(TeamMember::class, 'team_id');
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
