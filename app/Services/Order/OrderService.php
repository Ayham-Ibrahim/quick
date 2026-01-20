<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\Driver;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Service;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
     * جلب طلبات المستخدم الحالي (مع Pagination)
     */
    public function getUserOrders(array $filters = []): LengthAwarePaginator
    {
        return Order::where('user_id', Auth::id())
            ->with(['items.product', 'items.store', 'driver', 'coupon'])
            ->when($filters['status'] ?? null, fn($q, $status) => $q->byStatus($status))
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * جلب طلبات المستخدم الحالي (Collection للدمج)
     */
    public function getUserOrdersCollection(array $filters = [])
    {
        return Order::where('user_id', Auth::id())
            ->with(['items.product', 'items.store', 'driver', 'coupon'])
            ->when($filters['status'] ?? null, fn($q, $status) => $q->byStatus($status))
            ->latest()
            ->get();
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

    /**
     * إعادة طلب طلبية سابقة (Reorder)
     * 
     * - فقط للطلبات المسلّمة
     * - يتم جلب الأسعار الحالية للمنتجات
     * - يتم التحقق من توفر المنتجات والكميات
     * - تُنشأ طلبية جديدة كلياً
     */
    public function reorderOrder(int $orderId, array $data = []): Order
    {
        $originalOrder = Order::where('user_id', Auth::id())
            ->with(['items.product', 'items.variant'])
            ->find($orderId);

        if (!$originalOrder) {
            $this->throwExceptionJson('الطلب غير موجود', 404);
        }

        if (!$originalOrder->can_reorder) {
            $this->throwExceptionJson('لا يمكن إعادة طلب هذه الطلبية إلا بعد التسليم', 400);
        }

        return \DB::transaction(function () use ($originalOrder, $data) {
            $user = Auth::user();
            $subtotal = 0;
            $unavailableItems = [];
            $orderItems = [];

            // التحقق من توفر المنتجات وحساب الأسعار الحالية
            foreach ($originalOrder->items as $item) {
                $product = $item->product;
                $variant = $item->variant;

                // التحقق من وجود المنتج
                if (!$product || !$product->is_accepted) {
                    $unavailableItems[] = $item->product_name . ' (غير متاح)';
                    continue;
                }

                // السعر الحالي (من variant أو product)
                $currentPrice = $variant 
                    ? (float) $variant->price 
                    : (float) $product->current_price;

                // التحقق من توفر الكمية
                $availableStock = $variant 
                    ? $variant->stock_quantity 
                    : ($product->quantity ?? PHP_INT_MAX);

                if ($availableStock < $item->quantity) {
                    if ($availableStock <= 0) {
                        $unavailableItems[] = $item->product_name . ' (نفد من المخزون)';
                        continue;
                    }
                    // استخدام الكمية المتاحة
                    $unavailableItems[] = $item->product_name . " (الكمية المتاحة: {$availableStock} بدلاً من {$item->quantity})";
                }

                // التحقق من المتغير إن وجد
                if ($variant && !$variant->is_active) {
                    $unavailableItems[] = $item->product_name . ' (المتغير غير متاح)';
                    continue;
                }

                $quantity = min($item->quantity, $availableStock);
                $lineTotal = $currentPrice * $quantity;
                $subtotal += $lineTotal;

                $orderItems[] = [
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'store_id' => $item->store_id,
                    'quantity' => $quantity,
                    'unit_price' => $currentPrice,
                    'discount_amount' => 0, // لا يوجد خصم في إعادة الطلب
                    'line_total' => round($lineTotal, 2),
                    'product_name' => $product->name,
                    'variant_details' => $item->variant_details,
                ];
            }

            // التحقق من وجود عناصر صالحة
            if (empty($orderItems)) {
                $this->throwExceptionJson('جميع المنتجات في الطلب الأصلي غير متاحة حالياً', 400);
            }

            // رسوم التوصيل
            $deliveryFee = $data['delivery_fee'] ?? $originalOrder->delivery_fee;

            // إنشاء الطلب الجديد
            $newOrder = Order::create([
                'user_id' => $user->id,
                'coupon_id' => null, // لا يُطبق الكوبون القديم
                'coupon_code' => null,
                'subtotal' => round($subtotal, 2),
                'discount_amount' => 0,
                'delivery_fee' => $deliveryFee,
                'total' => round($subtotal + $deliveryFee, 2),
                'status' => Order::STATUS_PENDING,
                'confirmation_expires_at' => now()->addMinutes(Order::DRIVER_CONFIRMATION_TIMEOUT_MINUTES),
                'delivery_address' => $data['delivery_address'] ?? $originalOrder->delivery_address,
                'requested_delivery_at' => $data['requested_delivery_at'] ?? null,
                'is_immediate_delivery' => $data['is_immediate_delivery'] ?? true,
                'notes' => $data['notes'] ?? null,
            ]);

            // إنشاء عناصر الطلب
            foreach ($orderItems as $itemData) {
                $newOrder->items()->create($itemData);
            }

            // خصم الكميات من المخزون
            foreach ($newOrder->items as $newItem) {
                if ($newItem->product_variant_id) {
                    ProductVariant::where('id', $newItem->product_variant_id)
                        ->decrement('stock_quantity', $newItem->quantity);
                } else {
                    Product::where('id', $newItem->product_id)
                        ->where('quantity', '>', 0)
                        ->decrement('quantity', $newItem->quantity);
                }
            }

            // TODO: إرسال إشعار للسائقين (Firebase)

            $result = $newOrder->load(['items.product', 'items.store']);

            // إضافة معلومات عن العناصر غير المتاحة
            if (!empty($unavailableItems)) {
                $result->unavailable_items_notice = $unavailableItems;
            }

            return $result;
        });
    }

    /* ═══════════════════════════════════════════════════════════════════
     * وظائف السائق - Driver Functions
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * جلب الطلبات المتاحة للتوصيل (المعلقة) - مع Pagination
     */
    public function getAvailableOrdersForDelivery(array $filters = []): LengthAwarePaginator
    {
        return Order::availableForDrivers()
            ->with(['items.product', 'items.store', 'user'])
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * جلب الطلبات المتاحة للتوصيل (Collection للدمج)
     */
    public function getAvailableOrdersCollection()
    {
        return Order::availableForDrivers()
            ->with(['items.product', 'items.store', 'user'])
            ->latest()
            ->get();
    }

    /**
     * جلب طلبات السائق الحالي (مع Pagination)
     */
    public function getDriverOrders(array $filters = []): LengthAwarePaginator
    {
        $driver = Auth::guard('driver')->user();

        return Order::forDriver($driver->id)
            ->with(['items.product', 'items.store', 'user'])
            ->when($filters['status'] ?? null, fn($q, $status) => $q->byStatus($status))
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * جلب طلبات السائق الحالي (Collection للدمج)
     */
    public function getDriverOrdersCollection(array $filters = [])
    {
        $driver = Auth::guard('driver')->user();

        return Order::forDriver($driver->id)
            ->with(['items.product', 'items.store', 'user'])
            ->when($filters['status'] ?? null, fn($q, $status) => $q->byStatus($status))
            ->latest()
            ->get();
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

        // التحقق من رصيد المحفظة
        if (!$driver->hasEnoughBalanceForDelivery()) {
            $this->throwExceptionJson('رصيد محفظتك غير كافٍ لقبول هذا الطلب', 400);
        }

        // التحقق من عدد الطلبات النشطة حسب نوع الطلب (فوري/مجدول)
        if ($order->is_immediate_delivery) {
            if (!$driver->canAcceptImmediateOrder()) {
                $this->throwExceptionJson('لديك طلب فوري قيد التوصيل بالفعل', 400);
            }
        } else {
            if (!$driver->canAcceptScheduledOrder()) {
                $this->throwExceptionJson('لقد وصلت للحد الأقصى من الطلبات المجدولة (3 طلبات)', 400);
            }
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

        // TODO: إرسال إشعار للمستخدم (Firebase)

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

        // TODO: إرسال إشعار للمستخدم (Firebase)

        return $order->fresh(['items.product', 'user']);
    }

    /**
     * السائق يلغي طلب مجدول (الطلبات الفورية لا يمكن إلغاؤها)
     * ⚠️ يتم إرسال البيانات للإدارة لمعالجة الحالة
     */
    public function cancelScheduledOrderByDriver(int $orderId, string $reason): array
    {
        $driver = Auth::guard('driver')->user();
        $order = $this->getDriverOrder($orderId);

        if (!$order->can_driver_cancel_delivery) {
            if ($order->is_immediate_delivery) {
                $this->throwExceptionJson('لا يمكن إلغاء الطلبات الفورية - فقط الإدارة تستطيع ذلك', 400);
            }
            $this->throwExceptionJson('لا يمكن إلغاء هذا الطلب في حالته الحالية', 400);
        }

        // إلغاء الطلب
        $order->markAsCancelled($reason);
        $order->refresh();

        // تجهيز البيانات للإدارة
        $adminNotificationData = [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number ?? "ORD-{$order->id}",
                'status' => $order->status,
                'total' => $order->total,
                'delivery_fee' => $order->delivery_fee,
                'delivery_address' => $order->delivery_address,
                'requested_delivery_at' => $order->requested_delivery_at?->toDateTimeString(),
                'items' => $order->items->map(fn($item) => [
                    'product_name' => $item->product?->name ?? $item->product_name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'store_name' => $item->store?->store_name ?? null,
                ])->toArray(),
            ],
            'user' => [
                'id' => $order->user->id,
                'name' => $order->user->user_name,
                'phone' => $order->user->phone,
            ],
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->first_name . ' ' . $driver->last_name,
                'phone' => $driver->phone,
            ],
            'cancellation_reason' => $reason,
            'cancelled_at' => now()->toDateTimeString(),
        ];

        // TODO: إرسال إشعار للإدارة (Firebase/Email/Slack)
        // event(new ScheduledOrderCancelledByDriver($adminNotificationData));

        return [
            'order' => $order->fresh(['items.product', 'user']),
            'admin_notification' => $adminNotificationData,
        ];
    }

    // ⚠️ ملاحظة: السائق لا يمكنه إلغاء الطلب الفوري بعد القبول
    // الإلغاء فقط من الإدارة عبر cancelOrderByAdmin()

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
     * جلب جميع الطلبات (للأدمن) - مع Pagination
     */
    public function getAllOrders(array $filters = []): LengthAwarePaginator
    {
        return Order::with(['items.product', 'items.store', 'user', 'driver', 'coupon'])
            ->when($filters['status'] ?? null, fn($q, $status) => $q->byStatus($status))
            ->when($filters['user_id'] ?? null, fn($q, $userId) => $q->where('user_id', $userId))
            ->when($filters['driver_id'] ?? null, fn($q, $driverId) => $q->where('driver_id', $driverId))
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * جلب جميع الطلبات (Collection للدمج)
     */
    public function getAllOrdersCollection(array $filters = [])
    {
        return Order::with(['items.product', 'items.store', 'user', 'driver', 'coupon'])
            ->when($filters['status'] ?? null, fn($q, $status) => $q->byStatus($status))
            ->when($filters['user_id'] ?? null, fn($q, $userId) => $q->where('user_id', $userId))
            ->when($filters['driver_id'] ?? null, fn($q, $driverId) => $q->where('driver_id', $driverId))
            ->latest()
            ->get();
    }

    /**
     * إلغاء طلب من الإدارة (يعمل في أي حالة ما عدا delivered/cancelled)
     */
    public function cancelOrderByAdmin(int $orderId, string $reason): Order
    {
        $order = Order::find($orderId);

        if (!$order) {
            $this->throwExceptionJson('الطلب غير موجود', 404);
        }

        if (!$order->can_admin_cancel) {
            $this->throwExceptionJson('لا يمكن إلغاء هذا الطلب', 400);
        }

        $order->markAsCancelled($reason);

        // TODO: إرسال إشعار للمستخدم والسائق (Firebase)

        return $order->fresh(['items.product', 'user', 'driver']);
    }

    /* ═══════════════════════════════════════════════════════════════════
     * وظائف النظام - System Functions
     * ═══════════════════════════════════════════════════════════════════ */

    // ملاحظة: لا نلغي الطلبات تلقائياً عند انتهاء الصلاحية
    // المستخدم يضغط "تأكيد الطلب" مرة أخرى لتجديد فترة الانتظار
}
