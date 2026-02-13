<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\Driver;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\AdminProfit;
use App\Services\Service;
use App\Services\NotificationService;
use App\Services\AdminProfitService;
use App\Services\Geofencing\GeofencingService;
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
    protected NotificationService $notificationService;
    protected GeofencingService $geofencingService;
    protected AdminProfitService $adminProfitService;

    public function __construct(
        NotificationService $notificationService,
        GeofencingService $geofencingService,
        AdminProfitService $adminProfitService
    ) {
        $this->notificationService = $notificationService;
        $this->geofencingService = $geofencingService;
        $this->adminProfitService = $adminProfitService;
    }
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
     * جلب الاحداثيات الحالية للسائق المسؤول عن طلب محدد (لصاحب الطلب فقط)
     */
    public function getDriverLocationForOrder(int $orderId): array
    {
        $order = Order::where('user_id', Auth::id())
            ->with('driver')
            ->find($orderId);

        if (!$order) {
            $this->throwExceptionJson('الطلب غير موجود', 404);
        }

        if (!$order->driver_id || !$order->driver) {
            $this->throwExceptionJson('لم يتم تعيين سائق لهذا الطلب بعد', 404);
        }

        $driver = $order->driver;

        if (!$driver->current_lat || !$driver->current_lng) {
            $this->throwExceptionJson('لم تتوفر بيانات الموقع للسائق بعد', 404);
        }

        return [
            'driverId' => $driver->id,
            'driverName' => $driver->driver_name,
            'phone' => $driver->phone,
            'driverImage' => $driver->driver_image,
            'lat' => (float) $driver->current_lat,
            'lng' => (float) $driver->current_lng,
            'updatedAt' => $driver->last_location_update?->toIso8601String(),
            'isOnline' => (bool) $driver->is_online,
        ];
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

        // Notify driver if order was assigned
        if ($order->driver_id) {
            $this->notificationService->notifyDriverOrderCancelledByUser($order->driver, $order);
        }

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

        // Notify eligible drivers about the order
        $eligibleDrivers = $this->geofencingService->getEligibleDriversForOrder($order);
        $this->notificationService->notifyDriversNewOrder($eligibleDrivers, $order);

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

        // Notify eligible drivers about the order
        $eligibleDrivers = $this->geofencingService->getEligibleDriversForOrder($order);
        $this->notificationService->notifyDriversNewOrder($eligibleDrivers, $order);

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
                'delivery_lat' => $data['delivery_lat'] ?? $originalOrder->delivery_lat,
                'delivery_lng' => $data['delivery_lng'] ?? $originalOrder->delivery_lng,
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

            // Notify user about the new order
            $this->notificationService->notifyUserOrderCreated($newOrder);

            // Notify eligible drivers about the new order
            $eligibleDrivers = $this->geofencingService->getEligibleDriversForOrder($newOrder);
            $this->notificationService->notifyDriversNewOrder($eligibleDrivers, $newOrder);

            // Notify stores about the new order
            $this->notificationService->notifyStoresNewOrder($newOrder);

            $result = $newOrder->load(['items.product', 'items.store', 'user']);

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
     * status يمكن أن يكون: shipping, delivered, cancelled, pending/available
     * pending/available = الطلبات المتاحة في منطقة السائق الجغرافية (نفس الشيء)
     */
    public function getDriverOrders(array $filters = []): LengthAwarePaginator
    {
        $driver = Auth::guard('driver')->user();
        $status = $filters['status'] ?? null;
        $perPage = $filters['per_page'] ?? 15;

        // pending و available نفس الشيء = الطلبات المتاحة في المنطقة الجغرافية
        if ($status === 'available' || $status === 'pending') {
            $availableOrders = $this->geofencingService->getAvailableOrdersForDriver($driver);
            // تحويل Collection لـ Paginator يدوياً
            $page = request()->get('page', 1);
            $items = $availableOrders->forPage($page, $perPage);
            return new LengthAwarePaginator(
                $items->values(),
                $availableOrders->count(),
                $perPage,
                $page,
                ['path' => request()->url()]
            );
        }

        return Order::forDriver($driver->id)
            ->with(['items.product', 'items.store', 'user'])
            ->when($status, fn($q, $s) => $q->byStatus($s))
            ->latest()
            ->paginate($perPage);
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
     * جلب طلبات السائق مجمّعة حسب الحالة
     * تشمل: الطلبات المسندة للسائق + الطلبات المتاحة في منطقته
     */
    public function getDriverOrdersGrouped(): array
    {
        $driver = Auth::guard('driver')->user();

        // الطلبات المسندة للسائق (حسب الحالة)
        $myOrders = Order::forDriver($driver->id)
            ->with(['items.product', 'items.store', 'user'])
            ->latest()
            ->get();

        // الطلبات المتاحة في منطقة السائق الجغرافية
        $availableOrders = $this->geofencingService->getAvailableOrdersForDriver($driver);

        return [
            'shipping' => $myOrders->where('status', Order::STATUS_SHIPPING)->values(),
            'delivered' => $myOrders->where('status', Order::STATUS_DELIVERED)->values(),
            'cancelled' => $myOrders->where('status', Order::STATUS_CANCELLED)->values(),
            'available' => $availableOrders->values(),
        ];
    }

    /**
     * جلب تفاصيل طلب للسائق الحالي (عام)
     */
    public function getOrderDetailsForDriver(int $orderId): Order
    {
        return $this->getDriverOrder($orderId);
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
            // ->whereNull('driver_id')
            ->where('status', Order::STATUS_PENDING)
            ->update([
                'driver_id' => $driver->id,
                'driver_assigned_at' => now(),
                'status' => Order::STATUS_SHIPPING,
            ]);

        if (!$updated) {
            $this->throwExceptionJson('تم قبول هذا الطلب من سائق آخر', 400);
        }

        $order = $order->fresh(['items.product', 'items.store', 'user']);

        // Notify user that driver accepted the order
        $this->notificationService->notifyUserOrderAccepted($order);

        return $order;
    }

    /**
     * السائق يؤكد تسليم الطلب
     * (shipping → delivered)
     * 
     * - يتم خصم نسبة الربح من محفظة السائق
     * - يتم تسجيل أرباح الإدارة من السائق والمتاجر
     */
    public function confirmDeliveryByDriver(int $orderId): Order
    {
        $driver = Auth::guard('driver')->user();
        $order = $this->getDriverOrder($orderId);

        if ($order->status !== Order::STATUS_SHIPPING) {
            $this->throwExceptionJson('لا يمكن تأكيد التسليم في هذه الحالة', 400);
        }

        return DB::transaction(function () use ($order, $driver) {
            $order->markAsDelivered();

            // Process driver delivery profit (deduct from wallet)
            $this->adminProfitService->processDriverDeliveryProfit(
                $driver,
                AdminProfit::ORDER_TYPE_REGULAR,
                $order->id,
                (float) $order->delivery_fee
            );

            // Process store profits (record for each store in the order)
            $storeSubtotals = $order->items->groupBy('store_id')->map(function ($items) {
                return $items->sum('line_total');
            });

            foreach ($storeSubtotals as $storeId => $subtotal) {
                $this->adminProfitService->processStoreOrderProfit(
                    $storeId,
                    $order->id,
                    (float) $subtotal
                );
            }

            $order = $order->fresh(['items.product', 'user']);

            // Notify user that order was delivered
            $this->notificationService->notifyUserOrderDelivered($order);

            // Notify stores that order was delivered
            $this->notificationService->notifyStoresOrderDelivered($order);

            return $order;
        });
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

        // Notify user about cancellation
        $this->notificationService->notifyUserOrderCancelled($order, 'driver');

        // Notify admins about driver cancellation
        $this->notificationService->notifyAdminsDriverCancelledOrder($order, $reason);

        // Prepare data for admin dashboard
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

        // Notification already sent above via notifyAdminsDriverCancelledOrder()

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
            ->with([
                'items.product',
                'items.product.images',
                'items.variant.attributes.attribute',
                'items.variant.attributes.value',
                'items.store',
                'user',
                'coupon',
            ])
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

        // Notify user about admin cancellation
        $this->notificationService->notifyUserOrderCancelled($order, 'admin');

        // Notify driver if order was assigned
        if ($order->driver_id) {
            $this->notificationService->notifyDriverOrderCancelledByUser($order->driver, $order);
        }

        return $order->fresh(['items.product', 'user', 'driver']);
    }

    /**
     * جلب طلبات متجر معين (للأدمن)
     */
    public function getStoreOrders(int $storeId, array $filters = []): LengthAwarePaginator
    {
        $orders = Order::with(['items.product', 'items.store', 'user', 'driver', 'coupon'])
            ->whereHas('items', fn($q) => $q->where('store_id', $storeId))
            ->when($filters['status'] ?? null, fn($q, $status) => $q->byStatus($status))
            ->latest()
            ->paginate($filters['per_page'] ?? 15);

        // Attach store context to each order and its items so resources can render store-specific data
        $orders->setCollection($orders->getCollection()->each(function ($order) use ($storeId) {
            $order->store_context = $storeId;
            // Also mark each item with store_context to allow OrderItemResource to return store id
            $order->items->each(function ($item) use ($storeId) {
                $item->store_context = $storeId;
            });
        }));

        return $orders;
    }

    /* ═══════════════════════════════════════════════════════════════════
     * وظائف صاحب المتجر - Store Owner Functions
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * جلب كل طلبات المتجر (لصاحب المتجر)
     * 
     * يعرض جميع الطلبات مع إمكانية الفلترة حسب الحالة
     */
    public function getStoreOwnerActiveOrders(int $storeId, array $filters = []): LengthAwarePaginator
    {
        $orders = Order::with([
                'items.product.images',
                'items.variant.attributes.attribute',
                'items.variant.attributes.value',
                'items.store',
                'user',
                'driver',
                'coupon',
            ])
            ->whereHas('items', fn($q) => $q->where('store_id', $storeId))
            ->when($filters['status'] ?? null, fn($q, $status) => $q->byStatus($status))
            ->latest()
            ->paginate($filters['per_page'] ?? 15);

        // Attach store context for filtering items in resource
        $this->attachStoreContext($orders, $storeId);

        return $orders;
    }

    /**
     * جلب سجل طلبات المتجر (المكتملة فقط) مع الإحصائيات المالية
     * 
     * يعرض فقط الطلبات التي تم تسليمها (delivered)
     */
    public function getStoreOwnerOrdersHistory(int $storeId, array $filters = []): array
    {
        $orders = Order::with([
                'items.product.images',
                'items.variant.attributes.attribute',
                'items.variant.attributes.value',
                'items.store',
                'user',
                'driver',
                'coupon',
            ])
            ->whereHas('items', fn($q) => $q->where('store_id', $storeId))
            ->where('status', Order::STATUS_DELIVERED)
            ->latest()
            ->paginate($filters['per_page'] ?? 15);

        // Attach store context for filtering items in resource
        $this->attachStoreContext($orders, $storeId);

        // حساب الإحصائيات المالية للطلبات المكتملة فقط
        $financialStats = $this->calculateStoreFinancialStats($storeId);

        return [
            'orders' => $orders,
            'financial_stats' => $financialStats,
        ];
    }

    /**
     * جلب تفاصيل طلب معين لصاحب المتجر
     * 
     * يعرض فقط المنتجات التابعة للمتجر مع حسابات مالية دقيقة
     */
    public function getStoreOwnerOrderDetails(int $storeId, int $orderId): Order
    {
        $order = Order::with([
                'items.product.images',
                'items.variant.attributes.attribute',
                'items.variant.attributes.value',
                'items.store',
                'user',
                'driver',
                'coupon',
            ])
            ->whereHas('items', fn($q) => $q->where('store_id', $storeId))
            ->find($orderId);

        if (!$order) {
            $this->throwExceptionJson('الطلب غير موجود أو لا يحتوي على منتجات من متجرك', 404);
        }

        // Attach store context
        $order->store_context = $storeId;
        $order->items->each(fn($item) => $item->store_context = $storeId);

        return $order;
    }

    /**
     * حساب الإحصائيات المالية للمتجر
     * 
     * - إجمالي رصيد الطلبات (المبلغ قبل خصم نسبة الإدارة)
     * - نسبة الإدارة
     * - صافي رصيد المتجر
     */
    public function calculateStoreFinancialStats(int $storeId): array
    {
        // جلب الطلبات المكتملة التي تحتوي على منتجات من هذا المتجر
        $deliveredOrders = Order::with('items')
            ->whereHas('items', fn($q) => $q->where('store_id', $storeId))
            ->where('status', Order::STATUS_DELIVERED)
            ->get();

        $totalStoreRevenue = 0; // إجمالي المبيعات بعد خصم الكوبون
        $totalStoreCouponDiscount = 0; // إجمالي الخصومات

        foreach ($deliveredOrders as $order) {
            // فقط العناصر التابعة لهذا المتجر
            $storeItems = $order->items->where('store_id', $storeId);

            foreach ($storeItems as $item) {
                $totalStoreRevenue += (float) $item->line_total; // السعر بعد الخصم
                $totalStoreCouponDiscount += (float) $item->discount_amount;
            }
        }

        // جلب نسبة أرباح الإدارة من الطلبات
        $adminProfitPercentage = \App\Models\ProfitRatios::getValueByTag('order_profit_percentage') ?? 10;
        $adminProfitAmount = round($totalStoreRevenue * ($adminProfitPercentage / 100), 2);
        $netStoreBalance = round($totalStoreRevenue - $adminProfitAmount, 2);

        return [
            'total_orders_count' => $deliveredOrders->count(),
            'total_store_revenue' => round($totalStoreRevenue, 2), // إجمالي رصيد الطلبات
            'total_coupon_discount' => round($totalStoreCouponDiscount, 2), // إجمالي الخصومات
            'admin_profit_percentage' => $adminProfitPercentage,
            'admin_profit_amount' => $adminProfitAmount, // نسبة الإدارة
            'net_store_balance' => $netStoreBalance, // رصيد طلبات المتجر (صافي)
        ];
    }

    /**
     * Attach store context to orders collection
     */
    private function attachStoreContext(LengthAwarePaginator $orders, int $storeId): void
    {
        $orders->setCollection($orders->getCollection()->each(function ($order) use ($storeId) {
            $order->store_context = $storeId;
            $order->items->each(fn($item) => $item->store_context = $storeId);
        }));
    }

    /* ═══════════════════════════════════════════════════════════════════
     * وظائف النظام - System Functions
     * ═══════════════════════════════════════════════════════════════════ */

    // ملاحظة: لا نلغي الطلبات تلقائياً عند انتهاء الصلاحية
    // المستخدم يضغط "تأكيد الطلب" مرة أخرى لتجديد فترة الانتظار
}
