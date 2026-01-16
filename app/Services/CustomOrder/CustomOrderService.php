<?php

namespace App\Services\CustomOrder;

use App\Models\CustomOrder;
use App\Models\CustomOrderItem;
use App\Models\Driver;
use App\Models\ProfitRatios;
use App\Services\Service;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * خدمة إدارة الطلبات الخاصة (اطلب أي شيء)
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
class CustomOrderService extends Service
{
    /* ═══════════════════════════════════════════════════════════════════
     * الحسابات - Calculations
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * حساب سعر التوصيل بناءً على المسافة
     */
    public function calculateDeliveryFee(float $distanceKm): float
    {
        $kmPrice = ProfitRatios::getValueByTag('km_price') ?? 0;
        return $distanceKm * $kmPrice;
    }

    /* ═══════════════════════════════════════════════════════════════════
     * وظائف المستخدم - User Functions
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * إنشاء طلب خاص جديد (معلق مباشرة)
     */
    public function createOrder(array $data)
    {
        try {
            return DB::transaction(function () use ($data) {
                $user = Auth::user();

                // حساب سعر التوصيل
                $distanceKm = $data['distance_km'] ?? 0;
                $deliveryFee = $this->calculateDeliveryFee($distanceKm);

                // التحقق من وجود عناصر
                if (empty($data['items'])) {
                    $this->throwExceptionJson('يجب إضافة عنصر واحد على الأقل', 400);
                }

                // إنشاء الطلب مباشرة بحالة معلق
                $order = CustomOrder::create([
                    'user_id' => $user->id,
                    'delivery_fee' => $deliveryFee,
                    'distance_km' => $distanceKm,
                    'status' => CustomOrder::STATUS_PENDING,
                    'delivery_address' => $data['delivery_address'],
                    'delivery_lat' => $data['delivery_lat'] ?? null,
                    'delivery_lng' => $data['delivery_lng'] ?? null,
                    'is_immediate' => $data['is_immediate'] ?? true,
                    'scheduled_at' => $data['scheduled_at'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'confirmation_expires_at' => now()->addMinutes(CustomOrder::DRIVER_CONFIRMATION_TIMEOUT_MINUTES),
                ]);

                // إضافة العناصر
                $this->createOrderItems($order, $data['items']);

                // TODO: إرسال إشعار للسائقين

                return $order->load('items');
            });
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (\Throwable $th) {
            Log::error('CustomOrder creation error: ' . $th->getMessage());
            $this->throwExceptionJson('حدث خطأ أثناء إنشاء الطلب', 500);
        }
    }

    /**
     * إلغاء طلب (فقط في حالة معلق)
     */
    public function cancelOrder(int $orderId, ?string $reason = null)
    {
        $order = $this->getUserOrder($orderId);

        if (!$order->is_cancellable) {
            $this->throwExceptionJson('لا يمكن إلغاء هذا الطلب في حالته الحالية', 400);
        }

        $order->cancel($reason);

        return $order->fresh(['items', 'driver']);
    }

    /**
     * إعادة محاولة التوصيل بعد الإلغاء
     */
    public function retryDelivery(int $orderId)
    {
        $order = $this->getUserOrder($orderId);

        if ($order->status !== CustomOrder::STATUS_CANCELLED) {
            $this->throwExceptionJson('هذا الطلب غير ملغي', 400);
        }

        $order->retryDelivery();

        // TODO: إرسال إشعار للسائقين (Firebase)

        return $order->fresh(['items', 'driver']);
    }

    /**
     * إعادة إرسال الطلب للسائقين (تجديد فترة الانتظار)
     */
    public function resendToDrivers(int $orderId)
    {
        $order = $this->getUserOrder($orderId);

        if (!$order->can_resend_to_drivers) {
            $this->throwExceptionJson('لا يمكن إعادة إرسال هذا الطلب حالياً', 400);
        }

        $order->resendToDrivers();

        // TODO: إرسال إشعار للسائقين (Firebase)

        return $order->fresh(['items', 'driver']);
    }

    /**
     * جلب طلبات المستخدم الحالي
     */
    public function getUserOrders(array $filters = []): LengthAwarePaginator
    {
        $query = CustomOrder::where('user_id', Auth::id())
            ->with(['items', 'driver'])
            ->latest();

        if (!empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * جلب تفاصيل طلب معين للمستخدم
     */
    public function getOrderDetails(int $orderId)
    {
        return $this->getUserOrder($orderId);
    }

    /* ═══════════════════════════════════════════════════════════════════
     * وظائف السائق - Driver Functions
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * جلب الطلبات المتاحة للسائقين (المعلقة)
     */
    public function getAvailableOrdersForDrivers(array $filters = []): LengthAwarePaginator
    {
        return CustomOrder::availableForDrivers()
            ->with(['items', 'user'])
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * جلب طلبات السائق الحالي
     */
    public function getDriverOrders(array $filters = []): LengthAwarePaginator
    {
        $driver = Auth::guard('driver')->user();

        $query = CustomOrder::forDriver($driver->id)
            ->with(['items', 'user'])
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
    public function acceptOrderByDriver(int $orderId)
    {
        $driver = Auth::guard('driver')->user();

        if (!$driver->is_active) {
            $this->throwExceptionJson('حسابك غير نشط حالياً', 400);
        }

        $order = CustomOrder::find($orderId);

        if (!$order) {
            $this->throwExceptionJson('الطلب غير موجود', 404);
        }

        if (!$order->is_available_for_driver) {
            $this->throwExceptionJson('هذا الطلب غير متاح للقبول', 400);
        }

        // محاولة القبول مع التعامل مع Race Condition
        $updated = CustomOrder::where('id', $orderId)
            ->whereNull('driver_id')
            ->where('status', CustomOrder::STATUS_PENDING)
            ->update([
                'driver_id' => $driver->id,
                'driver_assigned_at' => now(),
                'status' => CustomOrder::STATUS_SHIPPING,
            ]);

        if (!$updated) {
            $this->throwExceptionJson('تم قبول هذا الطلب من سائق آخر', 400);
        }

        // TODO: إرسال إشعار للمستخدم

        return $order->fresh(['items', 'user']);
    }

    /**
     * السائق يؤكد تسليم الطلب
     * (shipping → delivered)
     */
    public function confirmDeliveryByDriver(int $orderId)
    {
        $order = $this->getDriverOrder($orderId);

        if ($order->status !== CustomOrder::STATUS_SHIPPING) {
            $this->throwExceptionJson('لا يمكن تأكيد التسليم في هذه الحالة', 400);
        }

        $order->markAsDelivered();

        // TODO: إرسال إشعار للمستخدم

        return $order->fresh(['items', 'user']);
    }

    /**
     * السائق يلغي التوصيل مع سبب
     * (shipping → cancelled)
     */
    public function cancelDeliveryByDriver(int $orderId, string $reason)
    {
        $order = $this->getDriverOrder($orderId);

        if ($order->status !== CustomOrder::STATUS_SHIPPING) {
            $this->throwExceptionJson('لا يمكن إلغاء التوصيل في هذه الحالة', 400);
        }

        if (empty(trim($reason))) {
            $this->throwExceptionJson('يجب تقديم سبب الإلغاء', 400);
        }

        $order->markAsCancelled($reason);

        // TODO: إرسال إشعار للمستخدم

        return $order->fresh(['items', 'user']);
    }

    /* ═══════════════════════════════════════════════════════════════════
     * وظائف النظام - System Functions
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * معالجة الطلبات المنتهية الصلاحية
     * يُحولها إلى cancelled
     */
    public function processExpiredOrders(): int
    {
        $expiredOrders = CustomOrder::expired()->get();

        foreach ($expiredOrders as $order) {
            $order->markAsCancelled('انتهت صلاحية البحث عن سائق');
            // TODO: إرسال إشعار للمستخدم
        }

        return $expiredOrders->count();
    }

    /* ═══════════════════════════════════════════════════════════════════
     * الوظائف المساعدة - Helper Functions
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * جلب طلب للمستخدم الحالي
     */
    protected function getUserOrder(int $orderId)
    {
        $order = CustomOrder::where('user_id', Auth::id())
            ->with(['items', 'driver'])
            ->find($orderId);

        if (!$order) {
            $this->throwExceptionJson('الطلب غير موجود', 404);
        }

        return $order;
    }

    /**
     * جلب طلب للسائق الحالي
     */
    protected function getDriverOrder(int $orderId)
    {
        $driver = Auth::guard('driver')->user();

        $order = CustomOrder::forDriver($driver->id)
            ->with(['items', 'user'])
            ->find($orderId);

        if (!$order) {
            $this->throwExceptionJson('الطلب غير موجود أو غير مسند إليك', 404);
        }

        return $order;
    }

    /**
     * إنشاء عناصر الطلب
     */
    protected function createOrderItems(CustomOrder $order, array $items): void
    {
        foreach ($items as $index => $item) {
            CustomOrderItem::create([
                'custom_order_id' => $order->id,
                'description' => $item['description'],
                'pickup_address' => $item['pickup_address'],
                'pickup_lat' => $item['pickup_lat'] ?? null,
                'pickup_lng' => $item['pickup_lng'] ?? null,
                'order_index' => $index,
            ]);
        }
    }
}
