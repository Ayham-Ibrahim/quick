<?php

namespace App\Services;

use App\Jobs\SendScheduledOrderReminder;
use App\Models\CustomOrder;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * خدمة إدارة تذكيرات الطلبات المجدولة
 * 
 * تقوم بجدولة التذكيرات عند قبول السائق للطلب المجدول:
 * - التذكير الأول: قبل 30 دقيقة من موعد التسليم
 * - التذكير الثاني: قبل 10 دقائق من موعد التسليم
 * 
 * الشروط:
 * - الطلب مجدول (is_immediate = false)
 * - scheduled_at أو requested_delivery_at موجود
 * - driver_id معين
 */
class ScheduledReminderService
{
    /**
     * وقت التذكير الأول قبل الموعد (بالدقائق)
     */
    const FIRST_REMINDER_MINUTES = 30;

    /**
     * وقت التذكير الثاني قبل الموعد (بالدقائق)
     */
    const SECOND_REMINDER_MINUTES = 10;

    /**
     * جدولة تذكيرات لطلب عادي
     * 
     * @param Order $order
     * @return void
     */
    public function scheduleRemindersForOrder(Order $order): void
    {
        // فقط للطلبات المجدولة
        if ($order->is_immediate_delivery) {
            Log::debug("ScheduledReminderService: Skipping immediate order #{$order->id}");
            return;
        }

        // التحقق من وجود موعد التسليم والسائق
        if (!$order->requested_delivery_at || !$order->driver_id) {
            Log::debug("ScheduledReminderService: Skipping order #{$order->id} - no delivery time or driver");
            return;
        }

        $scheduledAt = Carbon::parse($order->requested_delivery_at);
        
        $this->scheduleReminders(
            'regular',
            $order->id,
            $order->driver_id,
            $scheduledAt
        );

        Log::info("ScheduledReminderService: Scheduled reminders for regular order", [
            'order_id' => $order->id,
            'driver_id' => $order->driver_id,
            'scheduled_at' => $scheduledAt->toDateTimeString(),
        ]);
    }

    /**
     * جدولة تذكيرات لطلب خاص
     * 
     * @param CustomOrder $order
     * @return void
     */
    public function scheduleRemindersForCustomOrder(CustomOrder $order): void
    {
        // فقط للطلبات المجدولة
        if ($order->is_immediate) {
            Log::debug("ScheduledReminderService: Skipping immediate custom order #{$order->id}");
            return;
        }

        // التحقق من وجود موعد التسليم والسائق
        if (!$order->scheduled_at || !$order->driver_id) {
            Log::debug("ScheduledReminderService: Skipping custom order #{$order->id} - no scheduled_at or driver");
            return;
        }

        $scheduledAt = Carbon::parse($order->scheduled_at);
        
        $this->scheduleReminders(
            'custom',
            $order->id,
            $order->driver_id,
            $scheduledAt
        );

        Log::info("ScheduledReminderService: Scheduled reminders for custom order", [
            'order_id' => $order->id,
            'driver_id' => $order->driver_id,
            'scheduled_at' => $scheduledAt->toDateTimeString(),
        ]);
    }

    /**
     * جدولة التذكيرات
     * 
     * @param string $orderType 'regular' or 'custom'
     * @param int $orderId
     * @param int $driverId
     * @param Carbon $scheduledAt موعد التسليم المجدول
     */
    private function scheduleReminders(string $orderType, int $orderId, int $driverId, Carbon $scheduledAt): void
    {
        $now = Carbon::now();

        // حساب وقت التذكير الأول (30 دقيقة قبل الموعد)
        $firstReminderAt = $scheduledAt->copy()->subMinutes(self::FIRST_REMINDER_MINUTES);
        
        // حساب وقت التذكير الثاني (10 دقائق قبل الموعد)
        $secondReminderAt = $scheduledAt->copy()->subMinutes(self::SECOND_REMINDER_MINUTES);

        // جدولة التذكير الأول إذا لم يفت وقته
        if ($firstReminderAt->isAfter($now)) {
            $delay = $now->diffInSeconds($firstReminderAt);
            
            SendScheduledOrderReminder::dispatch(
                $orderType,
                $orderId,
                $driverId,
                SendScheduledOrderReminder::REMINDER_FIRST
            )->delay($delay)->onQueue('reminders');

            Log::debug("ScheduledReminderService: First reminder scheduled", [
                'order_type' => $orderType,
                'order_id' => $orderId,
                'delay_seconds' => $delay,
                'reminder_at' => $firstReminderAt->toDateTimeString(),
            ]);
        } else {
            Log::debug("ScheduledReminderService: First reminder time already passed", [
                'order_type' => $orderType,
                'order_id' => $orderId,
                'first_reminder_at' => $firstReminderAt->toDateTimeString(),
            ]);
        }

        // جدولة التذكير الثاني إذا لم يفت وقته
        if ($secondReminderAt->isAfter($now)) {
            $delay = $now->diffInSeconds($secondReminderAt);
            
            SendScheduledOrderReminder::dispatch(
                $orderType,
                $orderId,
                $driverId,
                SendScheduledOrderReminder::REMINDER_SECOND
            )->delay($delay)->onQueue('reminders');

            Log::debug("ScheduledReminderService: Second reminder scheduled", [
                'order_type' => $orderType,
                'order_id' => $orderId,
                'delay_seconds' => $delay,
                'reminder_at' => $secondReminderAt->toDateTimeString(),
            ]);
        } else {
            Log::debug("ScheduledReminderService: Second reminder time already passed", [
                'order_type' => $orderType,
                'order_id' => $orderId,
                'second_reminder_at' => $secondReminderAt->toDateTimeString(),
            ]);
        }
    }

    /**
     * إعادة تعيين حقول التذكير (عند تغيير السائق أو إلغاء)
     * 
     * @param Order|CustomOrder $order
     */
    public function resetReminders(Order|CustomOrder $order): void
    {
        $order->update([
            'reminder_sent_at' => null,
            'second_reminder_sent_at' => null,
        ]);

        Log::info("ScheduledReminderService: Reset reminders for order", [
            'order_type' => $order instanceof CustomOrder ? 'custom' : 'regular',
            'order_id' => $order->id,
        ]);
    }
}
