<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class InternalNotificationController extends AdminController
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (DatabaseNotification $notification) => $this->notificationPayload($notification))
            ->values();

        return response()->json([
            'status' => true,
            'data' => $notifications,
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        return response()->json([
            'status' => true,
            'data' => $this->notificationPayload($item->fresh()),
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'status' => true,
            'unread_count' => 0,
        ]);
    }

    private function notificationPayload(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->data['type'] ?? null,
            'title' => $notification->data['title'] ?? 'إشعار جديد',
            'message' => $notification->data['message'] ?? '',
            'url' => $notification->data['url'] ?? null,
            'application_id' => $notification->data['application_id'] ?? null,
            'read_at' => optional($notification->read_at)->toDateTimeString(),
            'created_at' => optional($notification->created_at)->toDateTimeString(),
        ];
    }
}
