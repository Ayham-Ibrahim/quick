<?php

namespace App\Services;

use App\Jobs\SendBroadcastNotification;
use App\Models\Notification;

/**
 * Broadcast Notification Service
 * 
 * Handles creation and management of broadcast notifications.
 * Notifications are sent via queue to avoid blocking.
 */
class BroadcastNotificationService extends Service
{
    /**
     * Create and send a broadcast notification.
     *
     * @param array $data
     * @return Notification
     */
    public function createAndSend(array $data): Notification
    {
        $notification = Notification::create([
            'title' => $data['title'],
            'content' => $data['content'],
            'target_types' => $data['target_types'],
        ]);

        // Dispatch to queue for async processing
        SendBroadcastNotification::dispatch($notification);

        return $notification;
    }

    /**
     * Get all notifications (paginated).
     *
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getAllNotifications(int $perPage = 15)
    {
        return Notification::latest()->paginate($perPage);
    }

    /**
     * Get notification by ID.
     *
     * @param int $id
     * @return Notification
     */
    public function getNotificationById(int $id): Notification
    {
        $notification = Notification::find($id);

        if (!$notification) {
            $this->throwExceptionJson('الإشعار غير موجود', 404);
        }

        return $notification;
    }

    /**
     * Delete notification.
     *
     * @param int $id
     * @return void
     */
    public function deleteNotification(int $id): void
    {
        $notification = $this->getNotificationById($id);
        
        // Only allow deletion of completed or failed notifications
        if (in_array($notification->status, [Notification::STATUS_PENDING, Notification::STATUS_SENDING])) {
            $this->throwExceptionJson('لا يمكن حذف الإشعار أثناء الإرسال', 400);
        }

        $notification->delete();
    }

    /**
     * Get available target types with their labels.
     *
     * @return array
     */
    public function getTargetTypes(): array
    {
        return Notification::getTargetTypes();
    }
}
