<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'employee_id',
        'department',
        'department_id',
        'phone',
        'avatar',
        'status',
        'last_login',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login' => 'datetime',
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Relasi struktur organisasi                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Divisi resmi user (tabel `departments`).
     *
     * Sengaja dinamai `division()` dan bukan `department()` karena kolom teks
     * lama `users.department` masih ada — bila relasinya dinamai `department`,
     * Eloquent akan mengembalikan isi kolom teks, bukan relasi.
     * Kolom teks itu kini hanya berfungsi sebagai cadangan (legacy).
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /** Tim kerja user. Satu user hanya boleh berada di satu tim. */
    public function team(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members', 'user_id', 'team_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function teamMemberships(): HasMany
    {
        return $this->hasMany(TeamMember::class, 'user_id');
    }

    /** Tim yang dipimpin user ini. */
    public function ledTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'leader_id');
    }

    /**
     * Nama divisi untuk ditampilkan: utamakan master `departments`, dan
     * jatuh ke teks lama bila akun belum ditautkan ke divisi mana pun.
     */
    public function departmentName(): ?string
    {
        return $this->division?->name ?: $this->department;
    }


    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isQa(): bool
    {
        return $this->hasRole('qa');
    }

    public function isEmployee(): bool
    {
        return $this->hasRole('employee');
    }

    public function isIt(): bool
    {
        return $this->hasRole('it');
    }

    public function isSupervisor(): bool
    {
        return $this->hasRole('supervisor');
    }

    public function isProjectLeader(): bool
    {
        return $this->hasRole('project_leader');
    }

    public function isHr(): bool
    {
        return $this->hasRole('hr');
    }

    /**
     * Role fungsional yang dikenali aplikasi (7 total — PRD §11.1).
     *
     * @return list<string>
     */
    public static function functionalRoles(): array
    {
        return [
            'admin',
            'qa',
            'employee',
            'supervisor',
            'project_leader',
            'it',
            'hr',
        ];
    }

    /**
     * Hanya QA (dan pengelola konten lain yang diberi permission) yang boleh
     * mengelola isi informasi. Admin mengatur wadah aplikasi, bukan konten.
     *
     * PRD §1/§16 — "Admin TIDAK bertanggung jawab membuat artikel/informasi."
     */
    public function canManageInformation(): bool
    {
        return $this->can('information.create')
            || $this->can('information.update')
            || $this->can('information.publish')
            || $this->can('information.archive')
            || $this->can('information.version');
    }

    public function isActive(): bool
    {
        return $this->status === 'active' || $this->status === null;
    }

    public function getAvatarUrlAttribute(): string
    {
        if (! empty($this->avatar)) {
            return asset('storage/' . ltrim($this->avatar, '/'));
        }

        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name ?? 'User') . '&background=0d6efd&color=fff';
    }

    public function getInitialAttribute(): string
    {
        return strtoupper(substr($this->name ?? 'U', 0, 1));
    }

    public function getRoleLabelAttribute(): string
    {
        return self::roleLabelFor($this->roles->first()?->name);
    }

    /**
     * Label tampilan untuk sebuah nama role.
     *
     * Dipakai juga oleh form admin (dropdown role) supaya daftar role dan
     * labelnya selalu sinkron — sebelumnya form memakai ternary sendiri dan
     * role baru tampil sebagai "Karyawan".
     */
    public static function roleLabelFor(?string $role): string
    {
        return match ($role) {
            'admin' => 'Admin (Application Management)',
            'qa' => 'QA (Content Management)',
            'employee' => 'Karyawan',
            'supervisor' => 'Supervisor',
            'project_leader' => 'Project Leader',
            'it' => 'IT Support',
            'hr' => 'HR',
            default => 'Karyawan',
        };
    }

    /* ------------------------------------------------------------------ */
    /* Relations                                                           */
    /* ------------------------------------------------------------------ */

    public function informationItems(): HasMany
    {
        return $this->hasMany(InformationItem::class, 'created_by');
    }

    /**
     * Catatan kerja pribadi (PRD Catatan).
     *
     * AGENT: asisten | 2026-10-01 | tambah relasi | modul Catatan Kerja, fase 1 backend
     *
     * Kepemilikan lewat `user_id` adalah satu-satunya kunci akses modul ini,
     * jadi relasi ini sengaja tidak dilengkapi helper "catatan rekan kerja".
     */
    public function workNotes(): HasMany
    {
        return $this->hasMany(WorkNote::class);
    }
}
