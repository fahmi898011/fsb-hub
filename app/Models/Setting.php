<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Saklar fitur aplikasi (feature toggle).
 *
 * AGENT: asisten | 2026-10-16 | buat berkas baru | modul Pengaturan Fitur
 *
 * Dibaca di banyak tempat (middleware, policy, sidebar, controller), jadi
 * hasilnya di-cache selamanya dan dibuang setiap kali admin menyimpan
 * perubahan. Dengan begitu menyalakan/mematikan fitur cukup satu klik dan
 * langsung berlaku pada request berikutnya, tanpa deploy.
 *
 * `Schema::hasTable()` dijaga karena model ini dipakai juga oleh sidebar yang
 * dirender di SETIAP halaman. Bila kode sudah diperbarui tetapi migration
 * belum dijalankan (deploy bertahap), aplikasi tidak boleh ikut mati — saklar
 * hanya dianggap memakai nilai bawaan.
 */
class Setting extends Model
{
    /** Kunci cache untuk seluruh baris setting. */
    public const CACHE_KEY = 'settings:all';

    protected $fillable = [
        'key',
        'group',
        'label',
        'description',
        'value',
        'type',
    ];

    /* ------------------------------------------------------------------ */
    /* Pembacaan                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Seluruh setting, di-cache. Koleksi di-key berdasarkan `key` agar
     * pemanggilan `get()` tidak perlu query berulang.
     *
     * @return Collection<string, self>
     */
    public static function allCached(): Collection
    {
        try {
            if (! Schema::hasTable('settings')) {
                return collect();
            }

            return Cache::rememberForever(self::CACHE_KEY, function () {
                return static::query()->orderBy('group')->orderBy('key')->get()->keyBy('key');
            });
        } catch (Throwable) {
            // Database belum siap (mis. saat `artisan migrate` pertama kali).
            return collect();
        }
    }

    /** Nilai satu setting, sudah dikonversi sesuai `type`. */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = self::allCached()->get($key);

        if (! $setting instanceof self) {
            return $default;
        }

        return match ($setting->type) {
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOL),
            'integer' => (int) $setting->value,
            default => $setting->value,
        };
    }

    /** Pintasan untuk setting bertipe boolean. */
    public static function enabled(string $key, bool $default = false): bool
    {
        return (bool) self::get($key, $default);
    }

    /* ------------------------------------------------------------------ */
    /* Penulisan                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Simpan nilai baru lalu buang cache.
     *
     * Mengembalikan false bila kuncinya tidak dikenal: lebih baik gagal diam-diam
     * daripada membuat baris setting baru dari salah ketik nama kunci, yang akan
     * tampak seperti saklar yang "tidak berfungsi".
     */
    public static function put(string $key, mixed $value): bool
    {
        $setting = static::query()->where('key', $key)->first();

        if (! $setting instanceof self) {
            return false;
        }

        $setting->value = is_bool($value)
            ? ($value ? '1' : '0')
            : (string) $value;

        $setting->save();

        self::flush();

        return true;
    }

    /** Buang cache setting; dipanggil setelah perubahan. */
    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /* ------------------------------------------------------------------ */
    /* Turunan tampilan                                                    */
    /* ------------------------------------------------------------------ */

    /** Nilai boolean untuk ditampilkan sebagai saklar. */
    public function isOn(): bool
    {
        return filter_var($this->value, FILTER_VALIDATE_BOOL);
    }

    /** Label grup dalam bahasa Indonesia. */
    public function getGroupLabelAttribute(): string
    {
        return match ($this->group) {
            'ujian' => 'Ujian / Quiz',
            'quiz' => 'Quiz (arsip)',
            default => ucfirst((string) $this->group),
        };
    }
}
