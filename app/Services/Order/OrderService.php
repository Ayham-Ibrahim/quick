<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\Driver;
use App\Services\Service;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * خدمة إدارة الطلبات العادية (منتجات من المتاجر)
 * 
 * ═══════════════════════════════════════════════════════════════════════
 * الحالات الأربعة المبسطة:
 * ═══════════════════════════════════════════════════════════════════════
 * 1. pending    = معلق (بانتظار قبول سائق)
 * 2. shipping   = قيد التوصيل
 * 3. delivered  = تم التسليم
 * 4. cancelled  = ملغي/فشل (مع سبب)
 * ═══════════════════════════════════════════════════════════════════════
 */
class OrderService extends Service
{
    /* ═══════════════════════════════════════════════════════════════════
     * وظائف المستخدم - User Functions
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * جلب طلبات المستخدم الحالي
     */
    public function getUserOrders(array $filters = []): LengthAwarePaginator
    {
        $query = Order::where('user_id', Auth::id())
            ->with(['items.product', 'items.store', 'driver', 'coupon'])
            ->latest();

        if (!empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * جلب تفاصيل طلب معين للمستخدم
     */
    public function getOrderDetails(int $orderId): Order
    {
        $order = Order::where('user_id', Auth::id())
            ->with([
                'items.product.images',
                'items.variant.attributes.attribute',
                'items.variant.attributes.value',
                'items.store',
                'driver',
                'coupon',
            ])
            ->find($orderId);

        if (!$order) {
            $this->throwExceptionJson('الطلب غير موجود', 404);
        }

        return $order;
    }

    /**
     * إلغاء طلب من المستخدم (فقط في حالة معلق)
     */
    public function cancelOrder(int $orderId, ?string $reason = null): Order
    {
        $order = Order::where('user_id', Auth::id())->find($orderId);

        if (!$order) {
            $this->throwExceptionJson('الطلب غير موجود', 404);
        }

        if (!$order->is_cancellable) {
            $this->throwExceptionJson('لا يمكن إلغاء هذا الطلب في حالته الحالية', 400);
        }

        $order->cancel($reason);

        return $order->fresh(['items.product', 'driver']);
    }

    /**
     * إعادة إرسال الطلب للسائقين (تجديد فترة الانتظار)
     */
    public function resendToDrivers(int $orderId): Order
    {
        $order = Order::where('user_id', Auth::id())->find($orderId);

        if (!$order) {
            $this->throwExceptionJson('الطلب غير موجود', 404);
        }

        if (!$order->can_resend_to_drivers) {
            $this->throwExceptionJson('لا يمكن إعادة إرسال هذا الطلب حالياً', 400);
        }

        $order->resendToDrivers();

        // TODO: إرسال إشعار للسائقين النشطين (Firebase)

        return $order->fresh(['items.product', 'items.store', 'driver']);
    }

    /**
     * إعادة محاولة التوصيل بعد الإلغاء
     */
    public function retryDelivery(int $orderId): Order
    {
        $order = Order::where('user_id', Auth::id())->find($orderId);

        if (!$order) {
            $this->throwExceptionJson('الطلب غير موجود', 404);
        }

        if ($order->status !== Order::STATUS_CANCELLED) {
            $this->throwExceptionJson('هذا الطلب غير ملغي', 400);
        }

        $order->retryDelivery();

        // TODO: إرسال إشعار للسائقين النشطين (Firebase)

        return $order->fresh(['items.product', 'items.store', 'driver']);
    }

    /* ═══════════════════════════════════════════════════════════════════
     * وظائف السائق - Driver Functions
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * جلب الطلبات المتاحة للتوصيل (المعلقة)
     */
    public function getAvailableOrdersForDelivery(array $filters = []): LengthAwarePaginator
    {
        return Order::availableForDrivers()
            ->with(['items.product', 'items.store', 'user'])
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * جلب طلبات السائق الحالي
     */
    public function getDriverOrders(array $filters = []): LengthAwarePaginator
    {
        $driver = Auth::guard('driver')->user();

        $query = Order::forDriver($driver->id)
            ->with(['items.product', 'items.store', 'user'])
            ->latest();

        if (!empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * السائق يقبل طلب ويبدأ التوصيل
     * (pending → shipping)
     */
    public function acceptOrderByDriver(int $orderId): Order
    {
        $driver = Auth::guard('driver')->user();

        if (!$driver->is_active) {
            $this->throwExceptionJson('حسابك غير نشط حالياً', 400);
        }

        $order = Order::find($orderId);

        if (!$order) {
            $this->throwExceptionJson('الطلب غير موجود', 404);
        }

        if (!$order->is_available_for_driver) {
            $this->throwExceptionJson('هذا الطلب غير متاح للقبول', 400);
        }

        // محاولة القبول مع التعامل مع Race Condition
        $updated = Order::where('id', $orderId)
            ->whereNull('driver_id')
            ->where('status', Order::STATUS_PENDING)
            ->update([
                'driver_id' => $driver->id,
                'driver_assigned_at' => now(),
                'status' => Order::STATUS_SHIPPING,
            ]);

        if (!$updated) {
            $this->throwExceptionJson('تم قبول هذا الطلب من سائق آخر', 400);
        }

        // TODO: إرسال إشعار للمستخدم

        return $order->fresh(['items.product', 'items.store', 'user']);
    }

    /**
     * السائق يؤكد تسليم الطلب
     * (shipping → delivered)
     */
    public function confirmDeliveryByDriver(int $orderId): Order
    {
        $order = $this->getDriverOrder($orderId);

        if ($order->status !== Order::STATUS_SHIPPING) {
            $this->throwExceptionJson('لا يمكن تأكيد التسليم في هذه الحالة', 400);
        }

        $order->markAsDelivered();

        // TODO: إرسال إشعار للمستخدم

        return $order->fresh(['items.product', 'user']);
    }

    /**
     * السائق يلغي التوصيل مع سبب
     * (shipping → cancelled)
     */
    public function cancelDeliveryByDriver(int $orderId, string $reason): Order
    {
        $order = $this->getDriverOrder($orderId);

        if ($order->status !== Order::STATUS_SHIPPING) {
            $this->throwExceptionJson('لا يمكن إلغاء التوصيل في هذه الحالة', 400);
        }

        if (empty(trim($reason))) {
            $this->throwExceptionJson('يجب تقديم سبب الإلغاء', 400);
        }

        $order->markAsCancelled($reason);

        // TODO: إرسال إشعار للمستخدم

        return $order->fresh(['items.product', 'user']);
    }

    /**
     * جلب طلب للسائق الحالي
     */
    protected function getDriverOrder(int $orderId): Order
    {
        $driver = Auth::guard('driver')->user();

        $order = Order::forDriver($driver->id)
            ->with(['items.product', 'user'])
            ->find($orderId);

        if (!$order) {
            $this->throwExceptionJson('الطلب غير موجود أو غير مسند إليك', 404);
        }

        return $order;
    }

    /* ═══════════════════════════════════════════════════════════════════
     * وظائف الإدارة - Admin Functions
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * جلب جميع الطلبات (للأدمن)
     */
    public function getAllOrders(array $filters = []): LengthAwarePaginator
    {
        $query = Order::with(['items.product', 'items.store', 'user', 'driver', 'coupon'])
            ->latest();

        if (!empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['driver_id'])) {
            $query->where('driver_id', $filters['driver_id']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /* ═══════════════════════════════════════════════════════════════════
     * وظائف النظام - System Functions
     * ═══════════════════════════════════════════════════════════════════ */

    // ملاحظة: لا نلغي الطلبات تلقائياً عند انتهاء الصلاحية
    // المستخدم يضغط "تأكيد الطلب" مرة أخرى لتجديد فترة الانتظار
}
