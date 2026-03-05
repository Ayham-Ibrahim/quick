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
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * انتهاء صلاحية الطلب المعلق بعد 60 دقيقة
 * 
 * ═══════════════════════════════════════════════════════════════════════
 * السلوك:
 * ═══════════════════════════════════════════════════════════════════════
 * 
 * بعد 60 دقيقة من إنشاء الطلب:
 * 1. إذا الطلب لا يزال pending و driver_id = null:
 *    - تحويل الحالة إلى cancelled
 *    - تسجيل سبب الإلغاء: "انتهت المهلة - لم يتوفر سائق"
 *    - إرسال إشعار للمستخدم
 * 
 * 2. إذا تم قبول الطلب (driver_id != null أو status != pending):
 *    - تجاهل الـ Job (no-op)
 * 
 * ═══════════════════════════════════════════════════════════════════════
 * Race Conditions & Multiple Workers:
 * ═══════════════════════════════════════════════════════════════════════
 * 
 * - WithoutOverlapping Middleware: يمنع تنفيذ نفس الـ Job مرتين
 * - DB Transaction: يضمن atomicity عند تغيير الحالة
 * - created_at validation: يمنع تنفيذ Jobs قديمة/مكررة
 * 
 * ═══════════════════════════════════════════════════════════════════════
 * Queue Failure Handling:
 * ═══════════════════════════════════════════════════════════════════════
 * 
 * - 3 محاولات مع Progressive Backoff (30s, 60s, 120s)
 * - failed() method يسجل الخطأ للمراجعة
 * - بعد 3 محاولات فاشلة، يتم نقل الـ Job إلى failed_jobs table
 * 
 * ═══════════════════════════════════════════════════════════════════════
 * Redis vs Database Queue:
 * ═══════════════════════════════════════════════════════════════════════
 * 
 * Redis (recommended for production):
 * ✓ أسرع (in-memory)
 * ✓ يدعم delayed jobs بكفاءة عالية
 * ✓ atomic operations
 * ✓ يدعم millions of jobs
 * 
 * Database:
 * ✓ لا يحتاج infrastructure إضافية
 * ✓ جيد للـ development
 * ⚠ أبطأ مع كثرة الـ jobs
 * ⚠ يحتاج index على delayed_until column
 * 
 * ═══════════════════════════════════════════════════════════════════════
 */
class ExpirePendingOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * عدد المحاولات
     */
    public int $tries = 3;

    /**
     * Progressive backoff
     */
    public array $backoff = [30, 60, 120];

    /**
     * Timeout
     */
    public int $timeout = 60;

    /**
     * سبب الإلغاء
     */
    const CANCELLATION_REASON = 'انتهت المهلة - لم يتوفر سائق';

    /**
     * Create a new job instance.
     *
     * @param string $orderType 'regular' أو 'custom'
     * @param int $orderId معرف الطلب
     * @param string $expirationRef وقت انتهاء صلاحية التأكيد (للتحقق)
     */
    public function __construct(
        public string $orderType,
        public int $orderId,
        public string $expirationRef
    ) {
    }

    /**
     * Middleware لمنع التنفيذ المتوازي
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("{$this->orderType}-{$this->orderId}-expire"))
                ->dontRelease()
                ->expireAfter(300),
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        Log::info("ExpirePendingOrder: Starting", [
            'order_type' => $this->orderType,
            'order_id' => $this->orderId,
            'expiration_ref' => $this->expirationRef,
        ]);

        // جلب الطلب
        $order = $this->getOrder();

        // === Fail-Safe Check #1: الطلب موجود؟ ===
        if (!$order) {
            Log::warning("ExpirePendingOrder: Order not found", [
                'order_type' => $this->orderType,
                'order_id' => $this->orderId,
            ]);
            return;
        }

        // === Fail-Safe Check #2: الطلب لا يزال معلق؟ ===
        if (!$this->isOrderStillPending($order)) {
            Log::info("ExpirePendingOrder: Order no longer pending - skipping expiration", [
                'order_id' => $this->orderId,
                'current_status' => $order->status,
                'driver_id' => $order->driver_id,
            ]);
            return;
        }

        // === Fail-Safe Check #3: التحقق من مرجع الصلاحية ===
        // يمنع تنفيذ Jobs قديمة بعد retry
        if ($order->confirmation_expires_at?->toISOString() !== $this->expirationRef) {
            Log::info("ExpirePendingOrder: Expiration ref mismatch - order was retried", [
                'order_id' => $this->orderId,
                'expected' => $this->expirationRef,
                'actual' => $order->confirmation_expires_at?->toISOString(),
            ]);
            return;
        }

        // === إلغاء الطلب وإشعار المستخدم ===
        $this->expireOrder($order, $notificationService);

        Log::info("ExpirePendingOrder: Order expired successfully", [
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
     * التحقق من أن الطلب لا يزال معلقاً
     */
    private function isOrderStillPending(Order|CustomOrder $order): bool
    {
        $isPending = $order->status === Order::STATUS_PENDING 
                     || $order->status === CustomOrder::STATUS_PENDING;
        
        $hasNoDriver = is_null($order->driver_id);

        return $isPending && $hasNoDriver;
    }

    /**
     * إلغاء الطلب وإرسال إشعار للمستخدم
     * يستخدم Transaction لضمان اتساق البيانات
     */
    private function expireOrder(Order|CustomOrder $order, NotificationService $notificationService): void
    {
        DB::transaction(function () use ($order, $notificationService) {
            // تحقق مزدوج داخل الـ Transaction (للتعامل مع Race Conditions)
            $freshOrder = $this->getOrder();
            
            if (!$freshOrder || !$this->isOrderStillPending($freshOrder)) {
                Log::info("ExpirePendingOrder: Order state changed during transaction - aborting", [
                    'order_id' => $this->orderId,
                ]);
                return;
            }

            // إلغاء الطلب
            $freshOrder->update([
                'status' => $freshOrder instanceof CustomOrder 
                    ? CustomOrder::STATUS_CANCELLED 
                    : Order::STATUS_CANCELLED,
                'cancellation_reason' => self::CANCELLATION_REASON,
            ]);

            Log::info("ExpirePendingOrder: Order cancelled", [
                'order_id' => $this->orderId,
                'reason' => self::CANCELLATION_REASON,
            ]);
        });

        // إشعار المستخدم (خارج الـ Transaction لتجنب blocking)
        $this->notifyUser($order, $notificationService);
    }

    /**
     * إرسال إشعار للمستخدم بإلغاء الطلب
     */
    private function notifyUser(Order|CustomOrder $order, NotificationService $notificationService): void
    {
        try {
            if ($order instanceof CustomOrder) {
                $notificationService->notifyUserCustomOrderCancelled($order, 'system');
            } else {
                $notificationService->notifyUserOrderCancelled($order, 'system');
            }

            Log::info("ExpirePendingOrder: User notified about cancellation", [
                'order_id' => $this->orderId,
                'user_id' => $order->user_id,
            ]);
        } catch (\Throwable $e) {
            // لا نفشل الـ Job إذا فشل الإشعار - الطلب تم إلغاؤه بالفعل
            Log::error("ExpirePendingOrder: Failed to notify user", [
                'order_id' => $this->orderId,
                'user_id' => $order->user_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * معالجة فشل الـ Job
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ExpirePendingOrder: Job failed permanently", [
            'order_type' => $this->orderType,
            'order_id' => $this->orderId,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // يمكن إضافة منطق إضافي هنا مثل:
        // - إرسال إشعار للـ Admin
        // - إضافة للـ monitoring system
    }

    /**
     * تحديد Queue المناسب
     */
    public function viaQueue(): string
    {
        return 'order-expiration';
    }
}
