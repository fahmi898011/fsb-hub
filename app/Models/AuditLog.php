<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;
use Spatie\Permission\Models\Role;

/**
 * Jejak audit (PRD §16).
 *
 * AGENT: asisten | 2026-10-10 | kolom jejak eksekusi + penjaga append-only | modul Audit Log, §11.15
 *
 * Tabel ini bersifat APPEND-ONLY: baris tidak boleh diubah atau dihapus.
 * Penjaganya ada di `booted()`; satu-satunya pengecualian adalah pelengkapan
 * `status_code` oleh `AuditHttpRequest::terminate()` yang menulis lewat query
 * builder (bukan model) supaya `updated_at` pun tidak ikut berubah.
 */
class AuditLog extends Model
{
    public const RESULT_SUCCESS = 'success';

    public const RESULT_FAILED = 'failed';

    public const RESULT_DENIED = 'denied';

    /**
     * Entitas audit: alias (`subject_type` untuk baris BARU) => kelas + label.
     *
     * Alias ditulis ke kolom `subject_type` oleh `AuditLogService` lewat
     * `subjectAlias()`, dan diresolusi kembali oleh `resolveSubjectClass()`.
     * Peta ini TIDAK didaftarkan sebagai morph map global — lihat catatan di
     * `AppServiceProvider::boot()`.
     *
     * Baris LAMA yang berisi FQCN tetap terbaca: `subjectLabel()`,
     * `subjectModule()`, dan `resolveSubjectClass()` menerima alias, nama
     * pendek, maupun FQCN.
     *
     * `module` dipakai sebagai cadangan saat aksi tidak berprefiks modul
     * (mis. aksi lama `published` pada `InformationItem`).
     */
    public const SUBJECTS = [
        'user' => ['class' => User::class, 'label' => 'Pengguna', 'module' => 'user'],
        'role' => ['class' => Role::class, 'label' => 'Peran', 'module' => 'role'],
        'department' => ['class' => Department::class, 'label' => 'Divisi', 'module' => 'department'],
        'team' => ['class' => Team::class, 'label' => 'Tim', 'module' => 'team'],
        'information_item' => ['class' => InformationItem::class, 'label' => 'Informasi', 'module' => 'information'],
        'information_category' => ['class' => InformationCategory::class, 'label' => 'Kategori Informasi', 'module' => 'information'],
        'conversation' => ['class' => Conversation::class, 'label' => 'Percakapan', 'module' => 'chat'],
        'kpi' => ['class' => Kpi::class, 'label' => 'KPI', 'module' => 'kpi'],
        'kpi_channel' => ['class' => Channel::class, 'label' => 'Kanal KPI', 'module' => 'kpi'],
        'scorecard' => ['class' => Scorecard::class, 'label' => 'Scorecard', 'module' => 'kpi'],
        'scorecard_version' => ['class' => ScorecardVersion::class, 'label' => 'Versi Scorecard', 'module' => 'kpi'],
        'scorecard_category' => ['class' => ScorecardCategory::class, 'label' => 'Kategori Scorecard', 'module' => 'kpi'],
        'scorecard_parameter' => ['class' => ScorecardParameter::class, 'label' => 'Parameter Scorecard', 'module' => 'kpi'],
        'qa_sampling_target' => ['class' => QaSamplingTarget::class, 'label' => 'Target Sampling', 'module' => 'kpi'],
        'qa_sampling_assessment' => ['class' => QaSamplingAssessment::class, 'label' => 'Assessment Sampling', 'module' => 'qa_sampling'],
        'tag' => ['class' => Tag::class, 'label' => 'Tag', 'module' => 'tag'],
        'quiz_template' => ['class' => QuizTemplate::class, 'label' => 'Template Quiz', 'module' => 'quiz'],
        'quiz_version' => ['class' => QuizVersion::class, 'label' => 'Versi Quiz', 'module' => 'quiz'],
        'quiz_attempt' => ['class' => QuizAttempt::class, 'label' => 'Pengerjaan Quiz', 'module' => 'quiz'],
        'question_bank' => ['class' => QuestionBank::class, 'label' => 'Bank Soal', 'module' => 'quiz'],
        'question_category' => ['class' => QuestionCategory::class, 'label' => 'Kategori Soal', 'module' => 'quiz'],
    ];

    /** Label modul untuk tampilan (kunci = prefiks aksi). */
    public const MODULE_LABELS = [
        'information' => 'Informasi',
        'user' => 'Pengguna',
        'role' => 'Peran',
        'department' => 'Divisi',
        'team' => 'Tim',
        'chat' => 'Chat',
        'kpi' => 'KPI',
        'quiz' => 'Quiz',
        'qa_sampling' => 'QA Sampling',
        'tag' => 'Tag',
        'auth' => 'Autentikasi',
        'access' => 'Hak Akses',
        'profile' => 'Profil',
        'system' => 'Sistem',
    ];

    protected $fillable = [
        'user_id',
        'actor_name',
        'actor_email',
        'actor_role',
        'action',
        'module',
        'category',
        'severity',
        'result',
        'description',
        'subject_type',
        'subject_id',
        'subject_label',
        'changed_fields',
        'old_values',
        'new_values',
        'method',
        'route_name',
        'url',
        'status_code',
        'request_id',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'changed_fields' => 'array',
        'status_code' => 'integer',
    ];

    /** Flag sementara: hanya untuk operasi internal yang diizinkan. */
    protected static bool $guardDisabled = false;

    protected static function booted(): void
    {
        static::updating(function (): void {
            if (! static::$guardDisabled) {
                throw new LogicException('Audit log bersifat append-only: baris tidak boleh diubah.');
            }
        });

        static::deleting(function (): void {
            if (! static::$guardDisabled) {
                throw new LogicException('Audit log bersifat append-only: baris tidak boleh dihapus.');
            }
        });
    }

    /**
     * Jalankan operasi yang memang perlu mengubah/menghapus baris audit
     * (mis. menaikkan `created_at` pada test, atau pembersihan terjadwal).
     *
     * @template TReturn
     *
     * @param  callable():TReturn  $callback
     * @return TReturn
     */
    public static function withoutGuard(callable $callback): mixed
    {
        static::$guardDisabled = true;

        try {
            return $callback();
        } finally {
            static::$guardDisabled = false;
        }
    }

    /**
     * Peta alias => kelas (lihat `SUBJECTS`).
     *
     * TIDAK didaftarkan sebagai morph map global: peta morph global mengubah
     * nilai `model_type` seluruh relasi polimorfik aplikasi (termasuk tabel
     * `model_has_roles` milik spatie) dan membuat `hasRole()` gagal untuk baris
     * lama. Dipakai di sini hanya untuk menerjemahkan alias dan kelas subjek.
     */
    public static function subjectMap(): array
    {
        return array_map(fn (array $meta) => $meta['class'], self::SUBJECTS);
    }

    /**
     * Alias pendek untuk sebuah kelas subjek (`App\Models\User` -> `user`).
     *
     * Kelas yang tidak dikenal dikembalikan apa adanya (FQCN) supaya entri
     * audit untuk model baru tidak pernah gagal ditulis.
     */
    public static function subjectAlias(?string $class): ?string
    {
        if ($class === null || $class === '') {
            return null;
        }

        $alias = array_search($class, self::subjectMap(), true);

        return is_string($alias) ? $alias : $class;
    }

    /**
     * Kelas subjek dari nilai `subject_type` apa pun: alias (`user`), nama
     * pendek (`User`), atau FQCN (`App\Models\User`). Mengembalikan null bila
     * tidak dikenali sehingga pemanggil tidak perlu menebak.
     *
     * @return class-string<Model>|null
     */
    public static function resolveSubjectClass(?string $type): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }

        foreach (self::SUBJECTS as $alias => $meta) {
            if ($type === $alias || $type === $meta['class'] || $type === class_basename($meta['class'])) {
                return $meta['class'];
            }
        }

        return class_exists($type) ? $type : null;
    }

    /**
     * Nilai `subject_type` yang mungkin dipakai untuk satu masukan filter.
     *
     * Menerima alias (`information_item`), nama pendek (`InformationItem`),
     * maupun FQCN (`App\Models\InformationItem`) — dan mengembalikan SEMUA
     * kandidat supaya baris lama (FQCN) dan baris baru (alias) sama-sama
     * terjaring.
     *
     * @return list<string>
     */
    public static function resolveSubjectTypes(string $input): array
    {
        $input = trim($input);

        if ($input === '') {
            return [];
        }

        $candidates = [$input];

        foreach (self::SUBJECTS as $alias => $meta) {
            $class = $meta['class'];

            if (strcasecmp($input, $alias) === 0
                || strcasecmp($input, $class) === 0
                || strcasecmp($input, class_basename($class)) === 0) {
                $candidates[] = $alias;
                $candidates[] = $class;
            }
        }

        return array_values(array_unique($candidates));
    }

    /** Label manusiawi untuk sebuah nilai `subject_type`. */
    public static function subjectLabel(?string $type): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }

        foreach (self::SUBJECTS as $alias => $meta) {
            if ($type === $alias || $type === $meta['class'] || $type === class_basename($meta['class'])) {
                return $meta['label'];
            }
        }

        return class_basename($type);
    }

    /** Modul asal sebuah nilai `subject_type` (cadangan bila aksi tanpa prefiks). */
    public static function subjectModule(?string $type): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }

        foreach (self::SUBJECTS as $alias => $meta) {
            if ($type === $alias || $type === $meta['class'] || $type === class_basename($meta['class'])) {
                return $meta['module'];
            }
        }

        return null;
    }

    /** Label manusiawi untuk sebuah modul (dipakai juga oleh dropdown filter). */
    public static function moduleLabelFor(?string $module): string
    {
        if ($module === null || $module === '') {
            return '—';
        }

        return self::MODULE_LABELS[$module] ?? Str::headline($module);
    }

    /** Label manusiawi untuk sebuah hasil eksekusi. */
    public static function resultLabelFor(?string $result): string
    {
        return match ($result) {
            self::RESULT_SUCCESS => 'Berhasil',
            self::RESULT_FAILED => 'Gagal',
            self::RESULT_DENIED => 'Ditolak',
            default => '—',
        };
    }

    /** Label manusiawi untuk sebuah tingkat kepentingan. */
    public static function severityLabelFor(?string $severity): string
    {
        return match ($severity) {
            'critical' => 'Kritis',
            'warning' => 'Perhatian',
            'notice' => 'Penting',
            'info' => 'Info',
            default => '—',
        };
    }

    /** Tampilan nilai apa pun (array/json dipadatkan) untuk tabel diff. */
    public static function displayValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Subjek entri audit (`$log->subject`).
     *
     * Bukan relasi `morphTo()`: `subject_type` menyimpan alias pendek
     * (`information_item`), dan morph map global sengaja TIDAK didaftarkan
     * (lihat `AppServiceProvider::boot()`). Karena itu alias diresolusi di
     * sini, dan baris lama ber-FQCN tetap ikut terbaca.
     */
    protected function subject(): Attribute
    {
        return Attribute::get(function (): ?Model {
            $class = self::resolveSubjectClass($this->subject_type);

            if ($class === null || $this->subject_id === null) {
                return null;
            }

            return $class::query()->find($this->subject_id);
        });
    }

    /**
     * Nama pelaku: relasi dulu, lalu potret saat kejadian, lalu email percobaan
     * (kasus gagal masuk), terakhir "Sistem".
     */
    public function actorLabel(): string
    {
        return $this->user?->name
            ?? $this->actor_name
            ?? $this->actor_email
            ?? 'Sistem';
    }

    public function actorEmail(): ?string
    {
        return $this->user?->email ?? $this->actor_email;
    }

    public function moduleLabel(): string
    {
        // Baris LAMA (ditulis sebelum §11.15) tidak punya kolom `module`;
        // modulnya ditebak dari prefiks aksi, lalu dari jenis entitasnya,
        // supaya daftar tidak penuh tanda "—".
        return self::moduleLabelFor($this->module ?? $this->derivedModule());
    }

    /** Modul hasil tebakan untuk baris lama. */
    private function derivedModule(): ?string
    {
        if (str_contains($this->action, '.')) {
            return Str::before($this->action, '.');
        }

        return self::subjectModule($this->subject_type);
    }

    /** Label entitas: label tersimpan, atau nama kelas + id sebagai cadangan. */
    public function subjectLabelText(): string
    {
        if ($this->subject_label !== null && $this->subject_label !== '') {
            return $this->subject_label;
        }

        if ($this->subject_type === null) {
            return '—';
        }

        $label = self::subjectLabel($this->subject_type) ?? class_basename($this->subject_type);

        return $this->subject_id ? $label.' #'.$this->subject_id : $label;
    }

    /** Nama pendek entitas untuk kolom "Entitas". */
    public function subjectTypeLabel(): string
    {
        if ($this->subject_type === null) {
            return '—';
        }

        return self::subjectLabel($this->subject_type) ?? class_basename($this->subject_type);
    }

    /** @return list<string> */
    public function changedFieldList(): array
    {
        return array_values(array_filter(
            $this->changed_fields ?? [],
            fn ($field) => is_string($field) && $field !== '',
        ));
    }

    /**
     * Baris diff untuk tampilan: gabungan kunci `old_values` + `new_values`
     * dengan penanda mana yang benar-benar berubah.
     *
     * @return list<array{field: string, old: mixed, new: mixed, changed: bool}>
     */
    public function diffRows(): array
    {
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];

        $rows = [];

        foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $key) {
            $before = $old[$key] ?? null;
            $after = $new[$key] ?? null;

            $rows[] = [
                'field' => (string) $key,
                'old' => $before,
                'new' => $after,
                'changed' => json_encode($before) !== json_encode($after),
            ];
        }

        return $rows;
    }

    public function resultLabel(): string
    {
        return self::resultLabelFor($this->result);
    }

    public function resultVariant(): string
    {
        return match ($this->result) {
            self::RESULT_SUCCESS => 'green',
            self::RESULT_FAILED => 'red',
            self::RESULT_DENIED => 'amber',
            default => 'gray',
        };
    }

    public function severityLabel(): string
    {
        return self::severityLabelFor($this->severity);
    }

    public function severityVariant(): string
    {
        return match ($this->severity) {
            'critical' => 'red',
            'warning' => 'amber',
            'notice' => 'brand',
            'info' => 'gray',
            default => 'gray',
        };
    }

    public function descriptionText(): string
    {
        return $this->description ?? '—';
    }
}

