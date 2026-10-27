<?php

namespace App\Services;

use App\Imports\EmployeeImport;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Impor data karyawan dari berkas Excel/CSV.
 *
 * Alur dipisah dua tahap agar admin bisa memeriksa dulu:
 *   1. analyze()  -> baca + validasi, kembalikan hasil per baris (tanpa tulis DB)
 *   2. commit()   -> simpan baris yang valid
 *
 * Aturan penting: bila ada SATU baris pun yang tidak valid, seluruh berkas
 * ditolak (belum boleh di-commit). Ini mencegah impor separuh jalan yang
 * membuat admin bingung mana yang sudah masuk.
 */
class EmployeeImportService
{
    /** Kolom wajib di berkas. */
    private const REQUIRED_COLUMNS = ['nama', 'email', 'role'];

    /** Role yang boleh dipakai di kolom "role". */
    private const ALLOWED_ROLES = [
        'admin',
        'qa',
        'employee',
        'supervisor',
        'project_leader',
        'it',
        'hr',
    ];

    /**
     * Password bawaan bila kolom password dikosongkan.
     */
    public const DEFAULT_PASSWORD = 'password';

    /**
     * Baca berkas dan validasi seluruh baris.
     *
     * @return array{
     *     rows: array<int, array{line:int,data:array<string,mixed>,errors:array<int,string>,status:string}>,
     *     missing_columns: array<int,string>,
     *     valid_count: int,
     *     invalid_count: int,
     *     total: int
     * }
     */
    public function analyze(string $path): array
    {
        $sheets = Excel::toArray(new EmployeeImport, $path);
        $raw = $sheets[0] ?? [];

        if (empty($raw)) {
            return [
                'rows' => [],
                'missing_columns' => self::REQUIRED_COLUMNS,
                'valid_count' => 0,
                'invalid_count' => 0,
                'total' => 0,
            ];
        }

        $headers = array_keys($raw[0]);
        $missing = $this->missingColumns($headers);

        $seenEmails = [];
        $rows = [];

        foreach ($raw as $index => $row) {
            // +2: baris 1 adalah judul kolom, dan Excel mulai dari 1.
            $line = $index + 2;

            $normalized = $this->normalizeRow($row);
            $errors = $this->validateRow($normalized, $line, $seenEmails);

            $rows[] = [
                'line' => $line,
                'data' => $normalized,
                'errors' => $errors,
                'status' => $errors === [] ? 'valid' : 'invalid',
            ];
        }

        $invalidCount = collect($rows)->where('status', 'invalid')->count();

        return [
            'rows' => $rows,
            'missing_columns' => $missing,
            'valid_count' => count($rows) - $invalidCount,
            'invalid_count' => $invalidCount,
            'total' => count($rows),
        ];
    }

    /**
     * Simpan baris-baris yang valid.
     *
     * @param  array<int, array{line:int,data:array<string,mixed>,errors:array<int,string>,status:string}>  $rows
     * @return int  jumlah karyawan yang dibuat
     */
    public function commit(array $rows): int
    {
        $created = 0;

        DB::transaction(function () use ($rows, &$created) {
            foreach ($rows as $row) {
                if ($row['status'] !== 'valid') {
                    continue;
                }

                $this->persist($row['data']);
                $created++;
            }
        });

        return $created;
    }

    /* ------------------------------------------------------------------ */
    /* Internal                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * @param  array<string, string>  $data
     */
    private function persist(array $data): void
    {
        $departmentName = trim((string) $data['departemen']);

        $user = User::create([
            'name' => $data['nama'],
            'email' => $data['email'],
            'employee_id' => $data['nip'] ?: null,
            'department' => $departmentName ?: null,
            // Nama divisi dari berkas ditautkan ke master `departments` supaya
            // rekap per divisi tidak pecah karena beda penulisan.
            'department_id' => $this->resolveDepartmentId($departmentName),
            'phone' => $data['telepon'] ?: null,
            'status' => $data['status'],
            'password' => bcrypt($data['password'] ?: self::DEFAULT_PASSWORD),
            'email_verified_at' => now(),
        ]);

        $user->assignRole($data['role']);
    }

    /**
     * Cari divisi berdasarkan nama (tanpa membedakan huruf besar/kecil).
     *
     * Divisi yang belum ada dibuatkan otomatis agar data dari berkas tidak
     * hilang; Admin dapat menggabungkannya kemudian lewat Master Divisi.
     */
    private function resolveDepartmentId(string $name): ?int
    {
        if ($name === '') {
            return null;
        }

        $existing = Department::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            return $existing->getKey();
        }

        return Department::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'status' => 'active',
        ])->getKey();
    }

    /**
     * Cari kolom wajib yang tidak ada di berkas.
     *
     * @param  array<int, string>  $headers
     * @return array<int, string>
     */
    private function missingColumns(array $headers): array
    {
        $normalized = array_map(fn ($header) => $this->normalizeKey((string) $header), $headers);

        return array_values(array_filter(
            self::REQUIRED_COLUMNS,
            fn (string $column) => ! in_array($column, $normalized, true),
        ));
    }

    /**
     * Samakan nama kolom ke bentuk baku.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, string>
     */
    private function normalizeRow(array $row): array
    {
        $mapped = [];

        foreach ($row as $key => $value) {
            $mapped[$this->normalizeKey((string) $key)] = is_string($value) ? trim($value) : $value;
        }

        return [
            'nama' => (string) ($mapped['nama'] ?? ''),
            'email' => Str::lower((string) ($mapped['email'] ?? '')),
            'nip' => (string) ($mapped['nip'] ?? $mapped['employee_id'] ?? ''),
            'departemen' => (string) ($mapped['departemen'] ?? $mapped['department'] ?? ''),
            'telepon' => (string) ($mapped['telepon'] ?? $mapped['phone'] ?? ''),
            'role' => Str::lower((string) ($mapped['role'] ?? '')),
            'status' => Str::lower((string) ($mapped['status'] ?? '')) ?: 'active',
            'password' => (string) ($mapped['password'] ?? ''),
        ];
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, int>  $seenEmails
     * @return array<int, string>
     */
    private function validateRow(array $data, int $line, array &$seenEmails): array
    {
        $errors = [];

        $validator = Validator::make($data, [
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'in:' . implode(',', self::ALLOWED_ROLES)],
            'status' => ['required', 'in:active,inactive'],
            'nip' => ['nullable', 'string', 'max:50'],
            'departemen' => ['nullable', 'string', 'max:255'],
            'telepon' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:8'],
        ], [
            'nama.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah terdaftar.',
            'role.required' => 'Role wajib diisi.',
            'role.in' => 'Role harus salah satu dari: ' . implode(', ', self::ALLOWED_ROLES) . '.',
            'status.in' => 'Status harus active atau inactive.',
            'password.min' => 'Password minimal 8 karakter.',
        ]);

        foreach ($validator->errors()->all() as $message) {
            $errors[] = $message;
        }

        // Email ganda DI DALAM satu berkas: validator unique hanya memeriksa
        // database, jadi perlu dicek manual.
        if ($data['email'] !== '') {
            if (isset($seenEmails[$data['email']])) {
                $errors[] = 'Email ganda di dalam berkas (sudah dipakai baris ' . $seenEmails[$data['email']] . ').';
            } else {
                $seenEmails[$data['email']] = $line;
            }
        }

        return $errors;
    }

    private function normalizeKey(string $key): string
    {
        $key = Str::lower(trim($key));
        $key = preg_replace('/[^a-z0-9]+/', '_', $key ?? '');

        return trim((string) $key, '_');
    }
}
