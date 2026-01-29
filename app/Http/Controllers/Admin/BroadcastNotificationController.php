<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBroadcastNotificationRequest;
use App\Http\Resources\NotificationResource;
use App\Services\BroadcastNotificationService;

/**
 * Admin Broadcast Notification Controller
 * 
 * Handles CRUD operations for broadcast notifications.
 * Only accessible by admin users.
 */
class BroadcastNotificationController extends Controller
{
    public function __construct(
        protected BroadcastNotificationService $notificationService
    ) {
    }

    /**
     * Get all notifications (paginated).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $notifications = $this->notificationService->getAllNotifications();

        return $this->paginate($notifications, 'تم جلب الإشعارات بنجاح');
    }

    /**
     * Create and send a new broadcast notification.
     *
     * @param StoreBroadcastNotificationRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreBroadcastNotificationRequest $request)
    {
        $notification = $this->notificationService->createAndSend($request->validated());

        return $this->success(
            new NotificationResource($notification),
            'تم إنشاء الإشعار وجاري إرساله',
            201
        );
    }

    /**
     * Get notification details.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $id)
    {
        $notification = $this->notificationService->getNotificationById($id);

        return $this->success(
            new NotificationResource($notification),
            'تم جلب تفاصيل الإشعار'
        );
    }

    /**
     * Delete a notification.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id)
    {
        $this->notificationService->deleteNotification($id);

        return $this->success(null, 'تم حذف الإشعار بنجاح');
    }

    /**
     * Get available target types.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTargetTypes()
    {
        $types = $this->notificationService->getTargetTypes();

        return $this->success($types, 'تم جلب أنواع المستهدفين');
    }
}
