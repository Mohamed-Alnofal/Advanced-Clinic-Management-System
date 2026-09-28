<?php

namespace Modules\Notifications\Services;

// use Modules\Notifications\Models\Notification;
use Modules\Notifications\Models\Notifications;
use Modules\Notifications\Services\FirebaseService;
use illuminate\Support\Facades\Log;
use Modules\Users\Models\FcmToken;

class NotificationService
{
    private ?FirebaseService $firebase = null;

    private function firebase(): ?FirebaseService
    {
        if ($this->firebase) {
            return $this->firebase;
        }

        try {
            $this->firebase = app(FirebaseService::class);

            return $this->firebase;
        } catch (\Throwable $e) {
            Log::error('FirebaseService unavailable', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function sendToUser(
        $user,
        string $title,
        string $body,
        array $data = [],
        string $type = 'general'
    ) {
        if (!$user) {
            return;
        }

        // 1. Always store in DB (in-app list)
        Notifications::create([
            'type' => $type,
            'notifiable_id' => $user->id,
            'notifiable_type' => get_class($user),
            'data' => [
                'title' => $title,
                'body' => $body,
                ...$data,
            ],
        ]);

        // 2. Push via FCM
        $tokens = FcmToken::where('user_id', $user->id)->pluck('token');

        if ($tokens->isEmpty()) {
            Log::warning('FCM skipped: no device tokens for user', [
                'user_id' => $user->id,
                'type' => $type,
            ]);

            return;
        }

        $firebase = $this->firebase();
        if (! $firebase) {
            return;
        }

        foreach ($tokens as $token) {
            try {
                // Include title/body in data so Flutter can show a local banner
                // even if the notification payload is stripped in some states.
                $firebase->sendToToken($token, $title, $body, [
                    'title' => $title,
                    'body' => $body,
                    ...$data,
                ]);
            } catch (\Exception $e) {
                $error = $e->getMessage();

                Log::error('FCM FAILED', [
                    'token' => $token,
                    'user_id' => $user->id,
                    'error' => $error,
                ]);

                $staleToken = str_contains(strtolower($error), 'notfound')
                    || str_contains(strtolower($error), 'unregistered')
                    || str_contains(strtolower($error), 'invalidregistration')
                    || str_contains(strtolower($error), 'requested entity was not found');

                if ($staleToken) {
                    FcmToken::where('token', $token)->delete();
                }
            }
        }
    }



    public function index($user)
{
    return Notifications::where('notifiable_id', $user->id)
        ->where('notifiable_type', get_class($user))
        ->latest()
        ->get()
        ->map(function ($notification) {

            return [

                'id' => $notification->id,

                'type' => $notification->type,

                'title' => $notification->data['title'] ?? null,

                'body' => $notification->data['body'] ?? null,

                // 'icon' => $this->getIcon($notification->type),

                // 'color' => $this->getColor($notification->type),

                'read' => !is_null($notification->read_at),

                'created_at' => $notification->created_at->diffForHumans(),
            ];
        });
}
    public function getUnread($user)
    {
        return Notifications::where('notifiable_id', $user->id)
            ->where('notifiable_type', get_class($user))
            ->whereNull('read_at')
            ->latest()
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->data['title'] ?? null,
                    'body' => $notification->data['body'] ?? null,
                    'read' => false,
                    'created_at' => $notification->created_at->diffForHumans(),
                ];
            });
    }

    public function getNotificationById(string $id, $user)
    {
        $notification = Notifications::where('id', $id)
            ->where('notifiable_id', $user->id)
            ->where('notifiable_type', get_class($user))
            ->first();

        if (! $notification) {
            return null;
        }

        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->data['title'] ?? null,
            'body' => $notification->data['body'] ?? null,
            'read' => ! is_null($notification->read_at),
            'created_at' => $notification->created_at->diffForHumans(),
        ];
    }

    public function markAsRead(string $notificationId): bool
    {
        $notification = Notifications::find($notificationId);

        if (!$notification) {
            return false;
        }

        $notification->markAsRead();

        return true;
    }

    public function markAllAsRead($user): void
    {
        Notifications::where('notifiable_id', $user->id)
            ->where('notifiable_type', get_class($user))
            ->whereNull('read_at')
            ->update([
                'read_at' => now()
            ]);
    }

    public function deleteNotification(string $id): bool
    {
        return Notifications::where('id', $id)->delete();
    }

    public function unreadCount($user): int
    {
        return Notifications::where('notifiable_id', $user->id)
            ->where('notifiable_type', get_class($user))
            ->whereNull('read_at')
            ->count();
    }


}