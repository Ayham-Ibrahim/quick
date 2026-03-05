<?php

namespace App\Jobs;

use App\Models\CustomOrder;
use App\Models\Order;
use App\Services\NotificationService;
use App\Services\Geofencing\GeofencingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * تذكير السائقين بالطلبات المعلقة بعد 30 دقيقة
 * 
 * ═══════════════════════════════════════════════════════════════════════
 * Event-Driven Architecture - لماذا هذا أفضل من Scheduler Polling؟
 * ═══════════════════════════════════════════════════════════════════════
 * 
 * 1. كفاءة الموارد:
 *    - لا يوجد استعلامات دورية على قاعدة البيانات
 *    - الـ Job يُنفذ فقط عند الحاجة (بعد 30 دقيقة بالضبط)
 * 
 * 2. قابلية التوسع:
 *    - يمكن تشغيل آلاف الـ Jobs بشكل متوازي
 *    - كل طلب له Job مستقل ← لا يوجد bottleneck
 * 
 * 3. دقة التوقيت:
 *    - بالضبط 30 دقيقة من وقت الإنشاء
 *    - Scheduler يفحص كل دقيقة ← قد يتأخر حتى 59 ثانية
 * 
 * 4. Fail-Safe:
 *    - التحقق من حالة الطلب قبل التنفيذ
 *    - إذا قبل السائق الطلب ← يتجاهل الـ Job نفسه
 *    - Retry strategy للتعامل مع الأخطاء المؤقتة
 * 
 * ═══════════════════════════════════════════════════════════════════════
 * الاستخدام:
 * ═══════════════════════════════════════════════════════════════════════
 * 
 * // عند إنشاء الطلب، dispatch مع delay 30 دقيقة
 * RemindDriversAboutPendingOrder::dispatch('regular', $order->id)
 *     ->delay(now()->addMinutes(30))
 *     ->onQueue('order-expiration');
 * 
 * ═══════════════════════════════════════════════════════════════════════
 */
class RemindDriversAboutPendingOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * عدد المحاولات
     */
    public int $tries = 3;

    /**
     * الانتظار بين المحاولات (ثانية)
     * Progressive backoff: 30s, 60s, 120s
     */
    public array $backoff = [30, 60, 120];

    /**
     * Timeout للـ Job (ثانية)
     */
    public int $timeout = 60;

    /**
     * Create a new job instance.
     *
     * @param string $orderType 'regular' أو 'custom'
     * @param int $orderId معرف الطلب
     * @param string $expirationRef وقت انتهاء صلاحية التأكيد (للتحقق من عدم التلاعب)
     */
    public function __construct(
        public string $orderType,
        public int $orderId,
        public string $expirationRef
    ) {
    }

    /**
     * Middleware لمنع التنفيذ المتوازي لنفس الطلب
     * يمنع Race Conditions عند تشغيل عدة Workers
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("{$this->orderType}-{$this->orderId}-reminder"))
                ->dontRelease() // لا تعيد الـ Job للقائمة إذا كان هناك تنفيذ جاري
                ->expireAfter(300), // 5 دقائق maximum lock
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(
        NotificationService $notificationService,
        GeofencingService $geofencingService
    ): void {
        Log::info("RemindDriversAboutPendingOrder: Starting", [
            'order_type' => $this->orderType,
            'order_id' => $this->orderId,
            'expiration_ref' => $this->expirationRef,
        ]);

        // جلب الطلب
        $order = $this->getOrder();

        // === Fail-Safe Check #1: الطلب موجود؟ ===
        if (!$order) {
            Log::warning("RemindDriversAboutPendingOrder: Order not found - possibly deleted", [
                'order_type' => $this->orderType,
                'order_id' => $this->orderId,
            ]);
            return; // No retry needed - order doesn't exist
        }

        // === Fail-Safe Check #2: الطلب لا يزال معلق؟ ===
        if (!$this->isOrderStillPending($order)) {
            Log::info("RemindDriversAboutPendingOrder: Order no longer pending - skipping", [
                'order_id' => $this->orderId,
                'current_status' => $order->status,
                'driver_id' => $order->driver_id,
            ]);
            return; // Order was accepted/cancelled - no action needed
        }

        // === Fail-Safe Check #3: التحقق من مرجع الصلاحية ===
        // يمنع تنفيذ Jobs قديمة بعد retry (confirmation_expires_at يتغير عند الـ retry)
        if ($order->confirmation_expires_at?->toISOString() !== $this->expirationRef) {
            Log::info("RemindDriversAboutPendingOrder: Expiration ref mismatch - order was retried", [
                'order_id' => $this->orderId,
                'expected' => $this->expirationRef,
                'actual' => $order->confirmation_expires_at?->toISOString(),
            ]);
            return;
        }

        // === إعادة إرسال الإشعارات للسائقين المؤهلين ===
        $this->resendNotificationsToDrivers($order, $notificationService, $geofencingService);

        Log::info("RemindDriversAboutPendingOrder: Completed successfully", [
            'order_id' => $this->orderId,
            'order_type' => $this->orderType,
        ]);
    }

    /**
     * جلب الطلب حسب النوع
     */
    private function getOrder(): Order|CustomOrder|null
    {
        return match ($this->orderType) {
            'regular' => Order::find($this->orderId),
            'custom' => CustomOrder::find($this->orderId),
            default => null,
        };
    }

    /**
     * التحقق من أن الطلب لا يزال معلقاً ولم يُقبل
     */
    private function isOrderStillPending(Order|CustomOrder $order): bool
    {
        // الطلب معلق إذا:
        // 1. الحالة = pending
        // 2. لا يوجد سائق معين (driver_id = null)
        $isPending = $order->status === Order::STATUS_PENDING 
                     || $order->status === CustomOrder::STATUS_PENDING;
        
        $hasNoDriver = is_null($order->driver_id);

        return $isPending && $hasNoDriver;
    }

    /**
     * إعادة إرسال الإشعارات للسائقين المؤهلين
     */
    private function resendNotificationsToDrivers(
        Order|CustomOrder $order,
        NotificationService $notificationService,
        GeofencingService $geofencingService
    ): void {
        // تجديد وقت انتهاء الصلاحية
        $order->resendToDrivers();

        // جلب السائقين المؤهلين
        if ($order instanceof CustomOrder) {
            $eligibleDrivers = $geofencingService->getEligibleDriversForCustomOrder($order);
            $sentCount = $notificationService->notifyDriversNewCustomOrder($eligibleDrivers, $order);
        } else {
            $eligibleDrivers = $geofencingService->getEligibleDriversForOrder($order);
            $sentCount = $notificationService->notifyDriversNewOrder($eligibleDrivers, $order);
        }

        Log::info("RemindDriversAboutPendingOrder: Sent reminder notifications", [
            'order_id' => $this->orderId,
            'order_type' => $this->orderType,
            'eligible_drivers' => $eligibleDrivers->count(),
            'sent_count' => $sentCount,
        ]);
    }

    /**
     * معالجة فشل الـ Job
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("RemindDriversAboutPendingOrder: Job failed permanently", [
            'order_type' => $this->orderType,
            'order_id' => $this->orderId,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * تحديد Queue المناسب
     */
    public function viaQueue(): string
    {
        return 'order-expiration';
    }
}
