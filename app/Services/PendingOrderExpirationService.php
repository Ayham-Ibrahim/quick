<?php

namespace App\Services;

use App\Models\Order;
use App\Models\CustomOrder;
use App\Jobs\RemindDriversAboutPendingOrder;
use App\Jobs\ExpirePendingOrder;
use Illuminate\Support\Facades\Log;

/**
 * خدمة إدارة انتهاء صلاحية الطلبات المعلقة
 * 
 * ═══════════════════════════════════════════════════════════════════════
 * Event-Driven Architecture للتعامل مع الطلبات المعلقة
 * ═══════════════════════════════════════════════════════════════════════
 * 
 * الجدول الزمني:
 * ─────────────────────────────────────────────────────────────────────
 * 
 *    0 min          30 min               60 min
 *      │               │                    │
 *      ▼               ▼                    ▼
 *   [Order Created]  [Reminder]         [Expiration]
 *      │               │                    │
 *      │               │                    ▼
 *      │               │            إذا لا يزال pending:
 *      │               │            - status → cancelled
 *      │               ▼            - إشعار المستخدم
 *      │          إذا لا يزال pending:
 *      │          - إعادة إرسال للسائقين
 *      ▼
 *   dispatch Jobs
 * 
 * ═══════════════════════════════════════════════════════════════════════
 * لماذا Event-Driven أفضل من Scheduler Polling؟
 * ═══════════════════════════════════════════════════════════════════════
 * 
 * Scheduler Polling (الطريقة التقليدية):
 * ─────────────────────────────────────────
 * // في app/Console/Kernel.php:
 * $schedule->command('orders:check-pending')->everyMinute();
 * 
 * المشاكل:
 * ✗ استعلام على كل الطلبات كل دقيقة
 * ✗ مع 10,000 طلب/يوم = 14,400,000 صف يُفحص يومياً!
 * ✗ ضغط كبير على قاعدة البيانات
 * ✗ تأخير حتى 59 ثانية في أسوأ الحالات
 * ✗ لا يتوسع مع زيادة الطلبات
 * 
 * Event-Driven (الحل الحالي):
 * ────────────────────────────
 * - Job واحد لكل طلب ← O(1) بدلاً من O(n)
 * - لا يوجد فحص دوري ← صفر ضغط على DB
 * - توقيت دقيق (30/60 دقيقة بالضبط)
 * - يتوسع أفقياً مع Queue Workers
 * 
 * ═══════════════════════════════════════════════════════════════════════
 * التكامل:
 * ═══════════════════════════════════════════════════════════════════════
 * 
 * // في CheckoutService عند إنشاء الطلب:
 * $this->pendingOrderExpirationService->scheduleExpirationJobs($order);
 * 
 * // في CustomOrderService عند إنشاء الطلب:
 * $this->pendingOrderExpirationService->scheduleExpirationJobs($order);
 * 
 * ═══════════════════════════════════════════════════════════════════════
 */
class PendingOrderExpirationService extends Service
{
    /**
     * وقت إرسال التذكير (دقيقة)
     */
    const REMINDER_DELAY_MINUTES = 30;

    /**
     * وقت انتهاء الصلاحية (دقيقة)
     */
    const EXPIRATION_DELAY_MINUTES = 60;

    /**
     * Queue المستخدم للـ Jobs
     */
    const QUEUE_NAME = 'order-expiration';

    /**
     * جدولة Jobs التذكير والانتهاء للطلب
     * 
     * يُستدعى مباشرة بعد إنشاء الطلب
     *
     * @param Order|CustomOrder $order
     * @return void
     */
    public function scheduleExpirationJobs(Order|CustomOrder $order): void
    {
        $orderType = $order instanceof CustomOrder ? 'custom' : 'regular';
        $expirationRef = $order->confirmation_expires_at->toISOString();

        // Job #1: تذكير السائقين بعد 30 دقيقة
        $this->scheduleReminderJob($orderType, $order->id, $expirationRef);

        // Job #2: انتهاء الصلاحية بعد 60 دقيقة
        $this->scheduleExpirationJob($orderType, $order->id, $expirationRef);

        Log::info("PendingOrderExpirationService: Jobs scheduled", [
            'order_type' => $orderType,
            'order_id' => $order->id,
            'expiration_ref' => $expirationRef,
            'reminder_at' => now()->addMinutes(self::REMINDER_DELAY_MINUTES)->toISOString(),
            'expiration_at' => now()->addMinutes(self::EXPIRATION_DELAY_MINUTES)->toISOString(),
        ]);
    }

    /**
     * جدولة Job التذكير
     */
    private function scheduleReminderJob(string $orderType, int $orderId, string $expirationRef): void
    {
        RemindDriversAboutPendingOrder::dispatch($orderType, $orderId, $expirationRef)
            ->delay(now()->addMinutes(self::REMINDER_DELAY_MINUTES))
            ->onQueue(self::QUEUE_NAME);
    }

    /**
     * جدولة Job الانتهاء
     */
    private function scheduleExpirationJob(string $orderType, int $orderId, string $expirationRef): void
    {
        ExpirePendingOrder::dispatch($orderType, $orderId, $expirationRef)
            ->delay(now()->addMinutes(self::EXPIRATION_DELAY_MINUTES))
            ->onQueue(self::QUEUE_NAME);
    }

    /**
     * إعادة جدولة Jobs جديدة (مثلاً عند إعادة محاولة التوصيل)
     * 
     * يُستدعى عند:
     * - retryDelivery()
     * - أي حالة تعيد الطلب إلى pending
     * 
     * ملاحظة: الـ Jobs القديمة ستتجاهل نفسها لأن confirmation_expires_at تغير
     *
     * @param Order|CustomOrder $order
     * @return void
     */
    public function rescheduleExpirationJobs(Order|CustomOrder $order): void
    {
        $orderType = $order instanceof CustomOrder ? 'custom' : 'regular';
        $expirationRef = $order->confirmation_expires_at->toISOString();
        
        // جدولة Jobs جديدة مع الـ expiration reference الجديد
        $this->scheduleReminderJob($orderType, $order->id, $expirationRef);
        $this->scheduleExpirationJob($orderType, $order->id, $expirationRef);

        Log::info("PendingOrderExpirationService: Jobs rescheduled for retry", [
            'order_type' => $orderType,
            'order_id' => $order->id,
            'expiration_ref' => $expirationRef,
        ]);
    }

    /**
     * الحصول على إعدادات الـ Queue الموصى بها
     * 
     * @return array
     */
    public static function getRecommendedQueueConfig(): array
    {
        return [
            // Queue driver (Redis موصى به للإنتاج)
            'driver' => 'redis', // أو 'database' للتطوير

            // عدد Workers
            'workers' => 2,

            // أمر تشغيل Worker
            'command' => 'php artisan queue:work --queue=' . self::QUEUE_NAME . ' --tries=3 --backoff=30,60,120',

            // Supervisor config (للإنتاج)
            'supervisor' => [
                'numprocs' => 2,
                'autorestart' => true,
                'startsecs' => 0,
                'stopwaitsecs' => 3600,
            ],
        ];
    }
}
