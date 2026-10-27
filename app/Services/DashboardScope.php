<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Batas data yang boleh dilihat seorang pengguna.
 *
 * AGENT: asisten | 2026-10-16 | buat berkas baru | modul Dashboard per peran
 *
 * Sebelum ini setiap halaman memutuskan sendiri siapa yang boleh melihat apa.
 * Akibatnya aturan yang sama dijawab berbeda: penilaian quiz supervisor
 * dibatasi ke tim yang ia pimpin, tetapi laporan QA Sampling — yang memuat
 * skor dan nama orang — terbuka untuk seluruh perusahaan. Kebocoran seperti
 * ini tidak terlihat sebagai bug karena halamannya tetap "berfungsi".
 *
 * Kelas ini menjawab satu pertanyaan saja: "baris milik siapa yang boleh
 * dihitung untuk pengguna ini?". Semua halaman yang menampilkan data lintas
 * orang memakai jawaban yang sama, sehingga menambah peran baru cukup diubah
 * di satu tempat.
 *
 * Aturan yang dipakai (mengikuti seeder peran yang sudah ada):
 *
 * - admin, qa, hr, it  : seluruh perusahaan. Mereka memang pihak yang mengatur
 *                        wadah, menilai, atau mengevaluasi lintas divisi.
 * - supervisor         : hanya anggota tim yang ia pimpin (ditambah dirinya).
 * - peran lain         : hanya dirinya sendiri.
 *
 * `project_leader` sengaja TIDAK termasuk pemegang data tim sampai izinnya
 * diputuskan (lihat catatan di RoleAndPermissionSeeder: perannya belum diberi
 * izin laporan). Bila nanti diberi `qa_sampling.report.view`, cukup tambahkan
 * 'project_leader' ke TEAM_ROLES dan seluruh halaman ikut menyesuaikan.
 *
 * PENTING: cakupan kosong berarti NOL baris, bukan "semua baris". Supervisor
 * yang belum memimpin tim harus melihat laporan kosong yang menjelaskan
 * keadaannya — bukan diam-diam melihat data seluruh perusahaan.
 */
class DashboardScope
{
    /** Peran yang memang bekerja lintas divisi. */
    public const GLOBAL_ROLES = ['admin', 'qa', 'hr', 'it'];

    /** Peran yang datanya dibatasi ke tim yang ia pimpin. */
    public const TEAM_ROLES = ['supervisor'];

    /**
     * @param  list<int>|null  $employeeIds  null = tidak dibatasi
     */
    private function __construct(
        private readonly ?User $user,
        private readonly ?array $employeeIds,
        private readonly int $teamCount,
    ) {}

    public static function for(?User $user): self
    {
        if ($user === null) {
            return new self(null, [], 0);
        }

        if ($user->hasAnyRole(self::GLOBAL_ROLES)) {
            return new self($user, null, 0);
        }

        if ($user->hasAnyRole(self::TEAM_ROLES)) {
            $teamIds = $user->ledTeams()->pluck('id');

            $memberIds = User::query()
                ->whereHas('team', fn (Builder $query) => $query->whereIn('teams.id', $teamIds))
                ->pluck('id')
                ->push($user->id)
                ->unique()
                ->values()
                ->all();

            return new self($user, $memberIds, $teamIds->count());
        }

        return new self($user, [$user->id], 0);
    }

    /** True bila pengguna boleh melihat data seluruh perusahaan. */
    public function isGlobal(): bool
    {
        return $this->employeeIds === null;
    }

    /**
     * Daftar id karyawan dalam cakupan, atau null bila tidak dibatasi.
     *
     * @return list<int>|null
     */
    public function employeeIds(): ?array
    {
        return $this->employeeIds;
    }

    /**
     * Apakah satu orang berada di dalam cakupan pengguna ini?
     *
     * Dipakai halaman yang menerima id orang dari URL (mis. riwayat karyawan).
     * Tanpa pemeriksaan ini, cakupan pada query hanya membuat halaman kosong:
     * supervisor tetap bisa menebak keberadaan orang lain dari halaman yang
     * "berhasil dibuka". Menolaknya lebih jujur daripada menampilkan halaman
     * kosong yang terlihat seperti data hilang.
     */
    public function allows(int $userId): bool
    {
        return $this->employeeIds === null || in_array($userId, $this->employeeIds, true);
    }

    /** Jumlah orang dalam cakupan (seluruh pengguna aktif bila global). */
    public function count(): int
    {
        return $this->employeeIds === null ? User::count() : count($this->employeeIds);
    }

    /** Label singkat untuk kepala laporan/halaman. */
    public function label(): string
    {
        if ($this->isGlobal()) {
            return 'Seluruh perusahaan';
        }

        return $this->user?->hasAnyRole(self::TEAM_ROLES)
            ? 'Tim yang Anda pimpin'
            : 'Hasil Anda sendiri';
    }

    /**
     * Terapkan cakupan ke array filter laporan.
     *
     * `employee_ids` selalu ditulis bila cakupan terbatas — termasuk array
     * kosong — supaya lapisan query menerjemahkannya menjadi "tidak ada baris"
     * alih-alih mengabaikan filter dan membuka seluruh data.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function apply(array $filters): array
    {
        if ($this->employeeIds !== null) {
            $filters['employee_ids'] = $this->employeeIds;
        }

        return $filters;
    }

    /** Terapkan cakupan langsung ke query yang punya kolom karyawan. */
    public function constrain(Builder $query, string $column = 'employee_id'): Builder
    {
        if ($this->employeeIds === null) {
            return $query;
        }

        return $query->whereIn($column, $this->employeeIds);
    }

    /**
     * Kalimat penjelas untuk ditampilkan di halaman.
     *
     * Halaman yang menampilkan angka lebih kecil tanpa penjelasan akan
     * dikira rusak atau dikira datanya hilang. Null berarti tidak perlu
     * penjelasan (cakupan penuh).
     */
    public function notice(): ?string
    {
        if ($this->isGlobal()) {
            return null;
        }

        if (! $this->user?->hasAnyRole(self::TEAM_ROLES)) {
            return 'Halaman ini hanya menghitung hasil milik Anda sendiri.';
        }

        if ($this->teamCount === 0) {
            return 'Anda belum tercatat sebagai pemimpin tim, sehingga laporan ini hanya berisi hasil Anda sendiri. '
                .'Minta Admin menetapkan Anda sebagai pemimpin tim agar data anggota ikut dihitung.';
        }

        return 'Laporan ini dibatasi ke '.$this->teamCount.' tim yang Anda pimpin ('
            .count($this->employeeIds).' orang, termasuk Anda). Data di luar tim tidak dihitung.';
    }
}
