<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileService
{
    /**
     * AGENT: asisten | 2026-10-10 | catat perubahan profil ke audit | modul Audit Log, §11.15
     */
    public function __construct(private readonly AuditLogService $audit) {}

    public function updateProfile(array $data): User
    {
        $user = auth()->user();

        // Snapshot sebelum perubahan: `logChanges()` memakainya untuk mengisi
        // nilai LAMA dari kolom yang benar-benar berubah saja.
        $before = $user->toArray();

        $user->update($data);

        $this->audit->logChanges('profile.updated', $user, $before, [
            'module' => 'profile',
            'category' => 'data',
        ]);

        return $user->fresh();
    }

    public function updateAvatar(UploadedFile $file): string
    {
        $user = auth()->user();

        // Hapus avatar lama bila ada
        if (! empty($user->avatar) && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $file->store('avatars', 'public');

        $user->update(['avatar' => $path]);

        $this->audit->log('profile.avatar_changed', $user, [], [], [
            'module' => 'profile',
            'category' => 'data',
            'severity' => 'notice',
            'description' => 'Memperbarui foto profil',
        ]);

        return $path;
    }

    public function changePassword(string $newPassword): void
    {
        $user = auth()->user();

        $user->update(['password' => Hash::make($newPassword)]);

        // Kata sandi TIDAK pernah masuk log — hanya kejadiannya yang dicatat.
        $this->audit->log('auth.password_changed', $user, [], [], [
            'module' => 'auth',
            'category' => 'auth',
            'severity' => 'notice',
            'description' => 'Mengubah kata sandi akun',
        ]);
    }

    public function getActivityLogs(int $limit = 10)
    {
        return AuditLog::query()
            ->where('user_id', auth()->id())
            ->latest()
            ->limit($limit)
            ->get();
    }
}