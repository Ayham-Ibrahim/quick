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
use Carbon\Carbon;

/**
 * إرسال تذكير للسائق باقتراب موعد تسليم الطلب المجدول
 * 
 * يُجدول هذا الـ Job عند قبول السائق للطلب المجدول:
 * - التذكير الأول: قبل 30 دقيقة من scheduled_at
 * - التذكير الثاني: قبل 10 دقائق من scheduled_at
 * 
 * الشروط للتنفيذ:
 * - الطلب ليس delivered أو cancelled
 * - السائق لا يزال معيناً للطلب
 * - لم يُرسل التذكير من قبل
 */
class SendScheduledOrderReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times to retry
     */
    public int $tries = 3;

    /**
     * Seconds to wait between retries
     */
    public array $backoff = [30, 60, 120];

    /**
     * Job timeout in seconds
     */
    public int $timeout = 30;

    /**
     * أنواع التذكير
     */
    const REMINDER_FIRST = 'first';     // 30 دقيقة قبل الموعد
    const REMINDER_SECOND = 'second';   // 10 دقائق قبل الموعد

    /**
     * Create a new job instance.
     *
     * @param string $orderType 'regular' or 'custom'
     * @param int $orderId
     * @param int $driverId السائق المعين وقت جدولة الـ Job
     * @param string $reminderType 'first' or 'second'
     */
    public function __construct(
        public string $orderType,
        public int $orderId,
        public int $driverId,
        public string $reminderType = self::REMINDER_FIRST
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        Log::info("SendScheduledOrderReminder: Processing {$this->reminderType} reminder", [
            'order_type' => $this->orderType,
            'order_id' => $this->orderId,
            'driver_id' => $this->driverId,
        ]);

        // جلب الطلب حسب النوع
        $order = $this->getOrder();

        if (!$order) {
            Log::warning("SendScheduledOrderReminder: Order not found", [
                'order_type' => $this->orderType,
                'order_id' => $this->orderId,
            ]);
            return;
        }

        // التحقق من صلاحية إرسال التذكير
        if (!$this->shouldSendReminder($order)) {
            Log::info("SendScheduledOrderReminder: Reminder skipped (conditions not met)", [
                'order_id' => $this->orderId,
                'status' => $order->status,
                'current_driver' => $order->driver_id,
                'original_driver' => $this->driverId,
            ]);
            return;
        }

        // التحقق من عدم إرسال التذكير مسبقاً (idempotency)
        if ($this->reminderAlreadySent($order)) {
            Log::info("SendScheduledOrderReminder: Reminder already sent", [
                'order_id' => $this->orderId,
                'reminder_type' => $this->reminderType,
            ]);
            return;
        }

        // إرسال الإشعار
        try {
            $notificationService->notifyDriverScheduledOrderReminder(
                $order->driver,
                $order,
                $this->reminderType
            );

            // تسجيل وقت الإرسال
            $this->markReminderSent($order);

            Log::info("SendScheduledOrderReminder: Reminder sent successfully", [
                'order_type' => $this->orderType,
                'order_id' => $this->orderId,
                'driver_id' => $order->driver_id,
                'reminder_type' => $this->reminderType,
            ]);
        } catch (\Exception $e) {
            Log::error("SendScheduledOrderReminder: Failed to send reminder", [
                'order_id' => $this->orderId,
                'error' => $e->getMessage(),
            ]);
            throw $e; // للسماح بـ retry
        }
    }

    /**
     * جلب الطلب حسب النوع
     */
    private function getOrder(): Order|CustomOrder|null
    {
        if ($this->orderType === 'regular') {
            return Order::with('driver')->find($this->orderId);
        }

        return CustomOrder::with('driver')->find($this->orderId);
    }

    /**
     * التحقق من صلاحية إرسال التذكير
     */
    private function shouldSendReminder(Order|CustomOrder $order): bool
    {
        // الحالة ليست delivered أو cancelled
        $invalidStatuses = [
            Order::STATUS_DELIVERED,
            Order::STATUS_CANCELLED,
        ];

        if (in_array($order->status, $invalidStatuses)) {
            return false;
        }

        // السائق لا يزال معيناً وهو نفسه
        if (!$order->driver_id || $order->driver_id !== $this->driverId) {
            return false;
        }

        // التحقق من أن الطلب مجدول
        if ($this->orderType === 'regular') {
            if ($order->is_immediate_delivery) {
                return false;
            }
        } else {
            if ($order->is_immediate) {
                return false;
            }
        }

        return true;
    }

    /**
     * التحقق من إرسال التذكير مسبقاً
     */
    private function reminderAlreadySent(Order|CustomOrder $order): bool
    {
        if ($this->reminderType === self::REMINDER_FIRST) {
            return $order->reminder_sent_at !== null;
        }

        return $order->second_reminder_sent_at !== null;
    }

    /**
     * تسجيل وقت إرسال التذكير
     */
    private function markReminderSent(Order|CustomOrder $order): void
    {
        $field = $this->reminderType === self::REMINDER_FIRST
            ? 'reminder_sent_at'
            : 'second_reminder_sent_at';

        $order->update([$field => now()]);
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'scheduled-reminder',
            "order:{$this->orderType}:{$this->orderId}",
            "driver:{$this->driverId}",
            "reminder:{$this->reminderType}",
        ];
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addHours(2);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("SendScheduledOrderReminder: Job failed permanently", [
            'order_type' => $this->orderType,
            'order_id' => $this->orderId,
            'driver_id' => $this->driverId,
            'reminder_type' => $this->reminderType,
            'error' => $exception->getMessage(),
        ]);
    }
}
