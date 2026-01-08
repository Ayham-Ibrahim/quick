<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\Driver;
use App\Services\Service;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Exceptions\HttpResponseException;

class OrderService extends Service
{
    /**
     * جلب طلبات المستخدم الحالي
     */
    public function getUserOrders(array $filters = [])
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
     * إلغاء طلب
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
     * جلب الطلبات المتاحة للتوصيل (للسائقين)
     */
    public function getAvailableOrdersForDelivery(array $filters = [])
    {
        $query = Order::withoutDriver()
            ->where('status', Order::STATUS_READY)
            ->with(['items.product', 'items.store', 'user'])
            ->latest();

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * جلب طلبات السائق الحالي
     */
    public function getDriverOrders(array $filters = [])
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
     * تعيين سائق للطلب
     */
    public function assignDriver(int $orderId, int $driverId): Order
    {
        $order = Order::find($orderId);

        if (!$order) {
            $this->throwExceptionJson('الطلب غير موجود', 404);
        }

        if ($order->has_driver) {
            $this->throwExceptionJson('تم تعيين سائق لهذا الطلب مسبقاً', 400);
        }

        if ($order->status !== Order::STATUS_READY) {
            $this->throwExceptionJson('الطلب غير جاهز للتوصيل', 400);
        }

        // التحقق من وجود السائق ونشاطه
        $driver = Driver::where('id', $driverId)
            ->where('is_active', true)
            ->first();

        if (!$driver) {
            $this->throwExceptionJson('السائق غير موجود أو غير نشط', 404);
        }

        $order->assignDriver($driverId);
        $order->updateStatus(Order::STATUS_SHIPPED);

        return $order->fresh(['items.product', 'driver', 'user']);
    }

    /**
     * السائق يقبل الطلب بنفسه
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

        if ($order->has_driver) {
            $this->throwExceptionJson('تم قبول هذا الطلب من سائق آخر', 400);
        }

        if ($order->status !== Order::STATUS_READY) {
            $this->throwExceptionJson('الطلب غير جاهز للتوصيل', 400);
        }

        $order->assignDriver($driver->id);
        $order->updateStatus(Order::STATUS_SHIPPED);

        return $order->fresh(['items.product', 'user']);
    }

    /**
     * تحديث حالة الطلب بواسطة السائق
     */
    public function updateOrderStatusByDriver(int $orderId, string $status): Order
    {
        $driver = Auth::guard('driver')->user();

        $order = Order::forDriver($driver->id)->find($orderId);

        if (!$order) {
            $this->throwExceptionJson('الطلب غير موجود أو غير مسند إليك', 404);
        }

        // التحقق من صحة انتقال الحالة
        $allowedTransitions = [
            Order::STATUS_SHIPPED => [Order::STATUS_DELIVERED],
        ];

        if (!isset($allowedTransitions[$order->status]) || 
            !in_array($status, $allowedTransitions[$order->status])) {
            $this->throwExceptionJson('لا يمكن تغيير الحالة إلى ' . $status, 400);
        }

        $order->updateStatus($status);

        return $order->fresh(['items.product', 'user']);
    }

    /**
     * جلب الطلبات للأدمن/المتجر
     */
    public function getAllOrders(array $filters = [])
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

    /**
     * تحديث حالة الطلب (للأدمن/المتجر)
     */
    public function updateOrderStatus(int $orderId, string $status): Order
    {
        $order = Order::find($orderId);

        if (!$order) {
            $this->throwExceptionJson('الطلب غير موجود', 404);
        }

        $validStatuses = [
            Order::STATUS_PENDING,
            Order::STATUS_CONFIRMED,
            Order::STATUS_PROCESSING,
            Order::STATUS_READY,
            Order::STATUS_SHIPPED,
            Order::STATUS_DELIVERED,
            Order::STATUS_CANCELLED,
        ];

        if (!in_array($status, $validStatuses)) {
            $this->throwExceptionJson('حالة غير صالحة', 400);
        }

        $order->updateStatus($status);

        return $order->fresh(['items.product', 'driver', 'user']);
    }
}
