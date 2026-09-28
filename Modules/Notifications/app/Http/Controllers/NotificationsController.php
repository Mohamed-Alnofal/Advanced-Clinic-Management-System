<?php

namespace Modules\Notifications\Http\Controllers;

// use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Core\Http\Controllers\Controller;
use Modules\Notifications\Services\NotificationService;

class NotificationsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
      public function unread(NotificationService $service)
    {
        return response()->json(
            $service->getUnread(auth()->user())
        );
    }

    public function index(NotificationService $service)
    {
        return response()->json(
            $service->index(auth()->user())
        );
    }

        public function show($id, NotificationService $service)
    {
        $notification = $service->getNotificationById(
            $id,
            auth()->user()
        );

        if (!$notification) {
            return response()->json([
                'message' => 'Notification not found'
            ], 404);
        }

        return response()->json($notification);
    }
    public function read($id, NotificationService $service)
    {
        return response()->json([
            'success' => $service->markAsRead($id)
        ]);
    }

    public function readAll(NotificationService $service)
    {
        $service->markAllAsRead(auth()->user());

        return response()->json([
            'message' => 'All notifications marked as read'
        ]);
    }

    public function delete($id, NotificationService $service)
    {
        return response()->json([
            'success' => $service->deleteNotification($id)
        ]);
    }

    public function count(NotificationService $service)
    {
        return response()->json([
            'unread' => $service->unreadCount(auth()->user())
        ]);
    }


}
