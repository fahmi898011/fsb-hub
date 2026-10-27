<?php

namespace App\Services;

use App\Http\Middleware\AuditHttpRequest;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

/**
 * Penulis jejak audit (PRD §16).
 *
 * AGENT: asisten | 2026-10-10 | lengkapi jejak eksekusi otomatis | modul Audit Log, §11.15
 *
 * `log()` mengisi SENDIRI apa yang bisa diketahui sistem — modul, deskripsi,
 * label entitas, kolom yang benar-benar berubah, potret pelaku, dan konteks
 * permintaan HTTP — sehingga pemanggil cukup menyebut aksi + entitas. Semua
 * pemanggil lama tetap sah: parameter `$meta` opsional dan hanya dipakai untuk
 * menimpa hasil otomatis (mis. `result`, `severity`, `description`).
 */
class AuditLogService
{
    /** Field yang TIDAK boleh masuk log. */
    private const REDACTED_KEYS = [
        'password', 'password_confirmation', 'current_password', 'new_password',
        'remember_token', 'api_token', 'token', '_token', 'access_token',
        'refresh_token', 'two_factor_secret', 'secret', 'authorization',
    ];

    /** Nilai pengganti untuk field yang disunting. */
    private const REDACTED_VALUE = '[disunting]';

    /** Panjang maksimal satu nilai teks di `old_values`/`new_values`. */
    private const MAX_VALUE_LENGTH = 2000;

    /** Panjang maksimal URL yang disimpan. */
    private const MAX_URL_LENGTH = 1000;

    /** Kunci yang dipakai untuk menebak label entitas. */
    private const LABEL_KEYS = [
        'title', 'name', 'subject', 'label', 'code', 'number',
        'document_number', 'email', 'slug',
    ];

    /**
     * Aksi lama tanpa prefiks modul -> bentuk baru. Dipetakan di sini (bukan
     * hanya di pemanggil) supaya baris dari pemanggil lama tetap konsisten di
     * dropdown filter.
     */
    private const LEGACY_ACTIONS = [
        'created' => 'information.created',
        'updated' => 'information.updated',
        'published' => 'information.published',
        'archived' => 'information.archived',
        'version_created' => 'information.version.created',
    ];

    /**
     * Kalimat baku untuk aksi yang penting. `:label` diganti label entitas.
     * Aksi di luar daftar ini tetap dapat kalimat lewat `VERBS`.
     */
    private const TEMPLATES = [
        'user.created' => 'Menambahkan pengguna: :label',
        'user.updated' => 'Mengubah data pengguna: :label',
        'user.deleted' => 'Menghapus pengguna: :label',
        'user.status_changed' => 'Mengubah status akun: :label',
        'user.import' => 'Mengimpor data karyawan dari berkas',
        'information.created' => 'Menambahkan informasi: :label',
        'information.updated' => 'Mengubah informasi: :label',
        'information.published' => 'Menerbitkan informasi: :label',
        'information.archived' => 'Mengarsipkan informasi: :label',
        'information.version.created' => 'Menambah versi dokumen: :label',
        'chat.channel.create' => 'Membuat kanal percakapan: :label',
        'chat.channel.update' => 'Mengubah kanal percakapan: :label',
        'chat.channel.archive' => 'Mengarsipkan kanal percakapan: :label',
        'chat.members.add' => 'Menambahkan anggota ke percakapan: :label',
        'chat.members.remove' => 'Mengeluarkan anggota dari percakapan: :label',
        'role.create' => 'Membuat peran: :label',
        'role.update' => 'Mengubah peran: :label',
        'role.delete' => 'Menghapus peran: :label',
        'department.create' => 'Menambah divisi: :label',
        'department.update' => 'Mengubah divisi: :label',
        'department.delete' => 'Menghapus divisi: :label',
        'team.create' => 'Menambah tim: :label',
        'team.update' => 'Mengubah tim: :label',
        'team.delete' => 'Menghapus tim: :label',
        'tag.update' => 'Mengganti nama tag: :label',
        'tag.delete' => 'Menghapus tag: :label',
        'auth.login' => 'Masuk ke aplikasi',
        'auth.logout' => 'Keluar dari aplikasi',
        'auth.login.failed' => 'Percobaan masuk gagal',
        'auth.lockout' => 'Percobaan masuk diblokir sementara (batas percobaan)',
        'auth.password_changed' => 'Mengubah kata sandi akun',
        'auth.account_deleted' => 'Menghapus akun sendiri',
        'profile.updated' => 'Memperbarui profil: :label',
        'profile.avatar_changed' => 'Memperbarui foto profil',
        'access.denied' => 'Akses ditolak',
        'quiz.attempt.submit' => 'Mengirim jawaban quiz',
    ];

    /** Kata kerja per segmen terakhir aksi (untuk aksi tanpa kalimat baku). */
    private const VERBS = [
        'create' => 'Menambahkan',
        'created' => 'Menambahkan',
        'update' => 'Mengubah',
        'updated' => 'Mengubah',
        'delete' => 'Menghapus',
        'deleted' => 'Menghapus',
        'publish' => 'Menerbitkan',
        'published' => 'Menerbitkan',
        'archive' => 'Mengarsipkan',
        'archived' => 'Mengarsipkan',
        'submit' => 'Mengirim',
        'submitted' => 'Mengirim',
        'review' => 'Meninjau',
        'reviewed' => 'Meninjau',
        'lock' => 'Mengunci',
        'locked' => 'Mengunci',
        'cancel' => 'Membatalkan',
        'cancelled' => 'Membatalkan',
        'activate' => 'Mengaktifkan',
        'deactivate' => 'Menonaktifkan',
        'status_changed' => 'Mengubah status',
        'import' => 'Mengimpor',
        'export' => 'Mengekspor',
        'add' => 'Menambahkan',
        'remove' => 'Menghapus',
        'answer' => 'Menjawab',
    ];

    /**
     * Catat satu kejadian audit.
     *
     * @param  string  $action  Nama aksi, idealnya `modul.objek.aksi` (mis. `user.status_changed`).
     * @param  Model|null  $subject  Entitas yang disentuh.
     * @param  array  $oldValues  Nilai sebelum (kosong untuk pembuatan).
     * @param  array  $newValues  Nilai sesudah (kosong untuk penghapusan).
     * @param  array  $meta  Penimpa opsional: `module`, `category`, `severity`, `result`,
     *                       `description`, `subject_label`, `actor`, `actor_name`,
     *                       `actor_email`, `method`, `route_name`, `url`, `status_code`,
     *                       `request_id`, `ip_address`.
     */
    public function log(string $action, ?Model $subject = null, array $oldValues = [], array $newValues = [], array $meta = []): AuditLog
    {
        $action = $this->normalizeAction($action, $subject);

        $oldValues = $this->prepareValues($oldValues);
        $newValues = $this->prepareValues($newValues);

        $module = $meta['module'] ?? $this->moduleFor($action, $subject);
        $changed = $this->changedFields($oldValues, $newValues);
        $label = $meta['subject_label'] ?? $this->labelFor($subject, $oldValues, $newValues);
        $actor = $meta['actor'] ?? Auth::user();

        $requestId = $meta['request_id'] ?? $this->requestId();
        $hasRequest = $requestId !== null;

        $log = AuditLog::create([
            'user_id' => $actor?->getKey(),
            'actor_name' => $actor?->name ?? ($meta['actor_name'] ?? null),
            'actor_email' => $actor?->email ?? ($meta['actor_email'] ?? null),
            'actor_role' => $this->roleFor($actor),

            'action' => $action,
            'module' => $module,
            'category' => $meta['category'] ?? $this->categoryFor($module),
            'severity' => $meta['severity'] ?? $this->severityFor($action),
            'result' => $meta['result'] ?? AuditLog::RESULT_SUCCESS,
            'description' => $meta['description'] ?? $this->describe($action, self::subjectType($subject), $label, $changed),

            'subject_type' => self::subjectType($subject),
            'subject_id' => $subject?->getKey(),
            'subject_label' => $label,
            'changed_fields' => $changed ?: null,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,

            'method' => $meta['method'] ?? ($hasRequest ? request()->getMethod() : null),
            'route_name' => $meta['route_name'] ?? ($hasRequest ? request()->route()?->getName() : null),
            'url' => $meta['url'] ?? ($hasRequest ? Str::limit(request()->fullUrl(), self::MAX_URL_LENGTH, '') : null),
            'status_code' => $meta['status_code'] ?? null,
            'request_id' => $requestId,

            'ip_address' => $meta['ip_address'] ?? Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 1000),
        ]);

        // Beri tahu `AuditHttpRequest::terminate()` bahwa permintaan ini punya
        // entri audit yang perlu dilengkapi `status_code`-nya.
        if ($hasRequest) {
            request()->attributes->set(AuditHttpRequest::AUDIT_WRITTEN_ATTRIBUTE, true);
        }

        return $log;
    }

    /**
     * Catat perubahan yang BENAR-BENAR terjadi pada model.
     *
     * Dipakai untuk aksi "ubah": memakai `getChanges()` supaya `old_values`
     * dan `new_values` hanya memuat kolom yang berubah — bukan seluruh baris —
     * sehingga kolom `changed_fields` dan tampilan diff tetap terbaca.
     *
     * @param  array  $before  Snapshot SEBELUM perubahan (mis. `$model->toArray()`).
     * @param  list<string>  $ignored  Kolom metadata yang dibuang dari diff.
     * @return AuditLog|null Null bila ternyata tidak ada kolom yang berubah
     *                       (supaya "menyimpan tanpa mengubah apa pun" tidak
     *                       memenuhi jejak audit dengan baris kosong).
     */
    public function logChanges(
        string $action,
        Model $subject,
        array $before,
        array $meta = [],
        array $ignored = ['created_at', 'created_by', 'updated_at', 'updated_by'],
    ): ?AuditLog {
        $changes = array_diff_key($subject->getChanges(), array_flip($ignored));

        if ($changes === []) {
            return null;
        }

        return $this->log($action, $subject, array_intersect_key($before, $changes), $changes, $meta);
    }

    /**
     * Aksi lama tanpa prefiks modul dinormalkan supaya dropdown filter dan
     * pencarian tidak terpecah dua (mis. `published` -> `information.published`).
     */
    private function normalizeAction(string $action, ?Model $subject): string
    {
        $action = trim($action);

        if (isset(self::LEGACY_ACTIONS[$action])) {
            return self::LEGACY_ACTIONS[$action];
        }

        // Aksi tanpa prefiks tetapi bersubjek yang dikenal: lengkapi modulnya.
        if (! str_contains($action, '.') && $subject !== null) {
            $module = AuditLog::subjectModule(self::subjectType($subject));

            if ($module !== null) {
                return $module.'.'.$action;
            }
        }

        return $action;
    }

    /** Bersihkan nilai: sunting field rahasia, potong teks yang kepanjangan. */
    private function prepareValues(array $values): array
    {
        $clean = [];

        foreach ($values as $key => $value) {
            if (in_array(Str::lower((string) $key), self::REDACTED_KEYS, true)) {
                $clean[$key] = self::REDACTED_VALUE;

                continue;
            }

            if (is_string($value) && mb_strlen($value) > self::MAX_VALUE_LENGTH) {
                $value = mb_substr($value, 0, self::MAX_VALUE_LENGTH).'…[dipotong]';
            }

            $clean[$key] = $value;
        }

        return $clean;
    }

    /**
     * Kolom yang benar-benar berubah.
     *
     * @return list<string>
     */
    private function changedFields(array $old, array $new): array
    {
        // Pembuatan (tanpa nilai lama) dan penghapusan (tanpa nilai baru) bukan
        // "perubahan kolom" — daftarnya sengaja kosong.
        if ($old === [] || $new === []) {
            return [];
        }

        $changed = [];

        foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $key) {
            if (json_encode($old[$key] ?? null) !== json_encode($new[$key] ?? null)) {
                $changed[] = (string) $key;
            }
        }

        return $changed;
    }

    /**
     * Nilai `subject_type` yang disimpan ke kolom: alias pendek bila kelasnya
     * dikenal (lihat `AuditLog::SUBJECTS`), FQCN bila belum terdaftar.
     *
     * Ditulis eksplisit (bukan `$subject->getMorphClass()`) karena morph map
     * global sengaja tidak didaftarkan — lihat `AppServiceProvider::boot()`.
     */
    private static function subjectType(?Model $subject): ?string
    {
        return $subject === null ? null : AuditLog::subjectAlias($subject::class);
    }

    /** Modul entri: prefiks aksi, atau modul entitas bila aksi tanpa prefiks. */
    private function moduleFor(string $action, ?Model $subject): string
    {
        if (str_contains($action, '.')) {
            return Str::before($action, '.');
        }

        return AuditLog::subjectModule(self::subjectType($subject)) ?? 'system';
    }

    private function categoryFor(string $module): string
    {
        return match ($module) {
            'auth' => 'auth',
            'access' => 'access',
            'system' => 'system',
            default => 'data',
        };
    }

    /**
     * Tingkat kepentingan: penghapusan & penolakan akses paling tinggi, aksi
     * yang mengubah keadaan publik di tengah, selebihnya informatif.
     */
    private function severityFor(string $action): string
    {
        if (Str::contains($action, ['delete', 'deleted', 'access.denied', 'auth.lockout'])) {
            return 'critical';
        }

        if (Str::contains($action, ['login.failed', 'cancel'])) {
            return 'warning';
        }

        if (Str::contains($action, [
            'create', 'publish', 'archive', 'submit', 'status_changed', 'lock',
            'activate', 'deactivate', 'login', 'logout', 'password', 'import',
            'add', 'remove', 'update',
        ])) {
            return 'notice';
        }

        return 'info';
    }

    /** Label entitas yang manusiawi: `auditLabel()`, kolom lazim, lalu nilai lama/baru. */
    private function labelFor(?Model $subject, array $oldValues, array $newValues): ?string
    {
        if ($subject !== null) {
            if (method_exists($subject, 'auditLabel')) {
                $label = $subject->auditLabel();

                if (is_string($label) && $label !== '') {
                    return Str::limit($label, 150, '');
                }
            }

            // Dibaca dari atribut mentah (bukan `getAttribute()`) supaya nama
            // kolom yang kebetulan sama dengan nama relasi tidak memicu query.
            $attributes = $subject->getAttributes();

            foreach (self::LABEL_KEYS as $key) {
                $value = $attributes[$key] ?? null;

                if (is_scalar($value) && (string) $value !== '') {
                    return Str::limit((string) $value, 150, '');
                }
            }
        }

        foreach ([$newValues, $oldValues] as $values) {
            foreach (self::LABEL_KEYS as $key) {
                if (isset($values[$key]) && is_scalar($values[$key]) && (string) $values[$key] !== '') {
                    return Str::limit((string) $values[$key], 150, '');
                }
            }
        }

        return null;
    }

    /** Satu kalimat manusiawi untuk entri audit (null bila tak bisa disusun). */
    private function describe(string $action, ?string $subjectType, ?string $label, array $changed): ?string
    {
        $template = self::TEMPLATES[$action] ?? null;

        if ($template !== null) {
            $text = $label !== null
                ? str_replace(':label', $label, $template)
                : str_replace(':label', '', $template);
        } else {
            $verb = self::VERBS[Str::afterLast($action, '.')] ?? null;

            if ($verb === null) {
                return null;
            }

            $noun = AuditLog::subjectLabel($subjectType);
            $text = $verb;

            if ($noun !== null && $label !== null) {
                $text .= ' '.$noun.': '.$label;
            } elseif ($noun !== null) {
                $text .= ' '.$noun;
            } elseif ($label !== null) {
                $text .= ' '.$label;
            }
        }

        // Rapikan pemisah yang menggantung saat label tidak ada.
        $text = rtrim(rtrim(trim($text), ' '), ':');

        if ($changed !== []) {
            $text .= ' — kolom: '.implode(', ', $changed);
        }

        return $text !== '' ? $text : null;
    }

    private function roleFor(?Model $actor): ?string
    {
        if ($actor === null || ! method_exists($actor, 'getRoleNames')) {
            return null;
        }

        try {
            return $actor->getRoleNames()->first();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Id permintaan HTTP yang sedang berjalan. Diisi middleware
     * `AuditHttpRequest`; null di luar permintaan web (console, queue) —
     * sekaligus penanda bahwa konteks HTTP tidak boleh diisi.
     */
    private function requestId(): ?string
    {
        $request = request();

        if (! $request instanceof \Illuminate\Http\Request) {
            return null;
        }

        $id = $request->attributes->get(AuditHttpRequest::REQUEST_ID_ATTRIBUTE);

        return is_string($id) && $id !== '' ? $id : null;
    }
}

