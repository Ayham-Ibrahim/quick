<?php

namespace App\Jobs;

use App\Models\CustomOrder;
use App\Models\Order;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job لإرسال إشعار اقتراب السائق
 * 
 * يُنفذ في الـ background لعدم تأخير response السائق
 */
class SendDriverApproachingNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * محاولات إعادة التنفيذ
     */
    public int $tries = 3;

    /**
     * Timeout بالثواني
     */
    public int $timeout = 30;

    public function __construct(
        public string $orderType,
        public int $orderId
    ) {}

    public function handle(NotificationService $notificationService): void
    {
        $order = $this->orderType === 'custom'
            ? CustomOrder::with(['user', 'driver'])->find($this->orderId)
            : Order::with(['user', 'driver'])->find($this->orderId);

        if (!$order) {
            Log::warning("SendDriverApproachingNotification: Order not found", [
                'order_type' => $this->orderType,
                'order_id' => $this->orderId,
            ]);
            return;
        }

        // التحقق من أن الإشعار لم يُرسل بالفعل (idempotency)
        if (!$order->driver_approaching_notified_at) {
            Log::debug("SendDriverApproachingNotification: Notification already sent or cancelled", [
                'order_type' => $this->orderType,
                'order_id' => $this->orderId,
            ]);
            return;
        }

        $notificationService->notifyUserDriverApproaching($order);

        Log::info("SendDriverApproachingNotification: Notification sent", [
            'order_type' => $this->orderType,
            'order_id' => $this->orderId,
            'user_id' => $order->user_id,
        ]);
    }

    /**
     * تحديد الـ queue
     */
    public function viaQueue(): string
    {
        return 'notifications';
    }

    /**
     * Tags للمراقبة في Horizon
     */
    public function tags(): array
    {
        return [
            'driver-approaching',
            'order:' . $this->orderType . ':' . $this->orderId,
        ];
    }
}
