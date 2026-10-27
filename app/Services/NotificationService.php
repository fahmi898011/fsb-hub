<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\User;

class NotificationService
{
    /**
     * Kirim notifikasi ke satu user.
     */
    public function notify(
        User|int $user,
        string $title,
        ?string $message = null,
        string $type = 'info',
        ?string $url = null,
    ): AppNotification {
        return AppNotification::create([
            'user_id' => $user instanceof User ? $user->id : $user,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'url' => $url,
        ]);
    }

    /**
     * Kirim notifikasi ke semua user dengan role tertentu.
     */
    public function notifyRole(
        string $role,
        string $title,
        ?string $message = null,
        string $type = 'info',
        ?string $url = null,
    ): void {
        User::role($role)->each(function (User $user) use ($title, $message, $type, $url) {
            $this->notify($user, $title, $message, $type, $url);
        });
    }

    public function unreadCount(User $user): int
    {
        return AppNotification::where('user_id', $user->id)->unread()->count();
    }
}