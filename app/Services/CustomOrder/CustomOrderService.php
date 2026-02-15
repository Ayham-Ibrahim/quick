<?php

namespace App\Services\CustomOrder;

use App\Models\CustomOrder;
use App\Models\CustomOrderItem;
use App\Models\Driver;
use App\Models\ProfitRatios;
use App\Models\AdminProfit;
use App\Services\Service;
use App\Services\NotificationService;
use App\Services\AdminProfitService;
use App\Services\Geofencing\GeofencingService;
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
                    //TODO: remove the driver id
                    // 'driver_id' => 15,
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

                // Notify user that order was created
                $this->notificationService->notifyUserCustomOrderCreated($order);

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

        // Notify driver if order was assigned
        if ($order->driver_id) {
            $this->notificationService->notifyDriverOrderCancelledByUser($order->driver, $order);
        }

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

        // Notify eligible drivers about the order
        $eligibleDrivers = $this->geofencingService->getEligibleDriversForCustomOrder($order);
        $this->notificationService->notifyDriversNewCustomOrder($eligibleDrivers, $order);

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

        // Notify eligible drivers about the order
        $eligibleDrivers = $this->geofencingService->getEligibleDriversForCustomOrder($order);
        $this->notificationService->notifyDriversNewCustomOrder($eligibleDrivers, $order);

        return $order->fresh(['items', 'driver']);
    }

    /**
     * جلب طلبات المستخدم الحالي (مع Pagination)
     */
    public function getUserOrders(array $filters = []): LengthAwarePaginator
    {
        return CustomOrder::where('user_id', Auth::id())
            ->with(['items', 'driver'])
            ->when($filters['status'] ?? null, fn($q, $status) => $q->byStatus($status))
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * جلب طلبات المستخدم الحالي (Collection للدمج)
     */
    public function getUserOrdersCollection(array $filters = [])
    {
        return CustomOrder::where('user_id', Auth::id())
            ->with(['items', 'driver'])
            ->when($filters['status'] ?? null, fn($q, $status) => $q->byStatus($status))
            ->latest()
            ->get();
    }

    /**
     * جلب تفاصيل طلب معين للمستخدم
     */
    public function getOrderDetails(int $orderId)
    {
        return $this->getUserOrder($orderId);
    }

    /**
     * جلب الاحداثيات الحالية للسائق المسؤول عن طلب خاص محدد (لصاحب الطلب فقط)
     */
    public function getDriverLocationForCustomOrder(int $orderId): array
    {
        $order = CustomOrder::where('user_id', Auth::id())
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
     * جلب جميع الطلبات الخاصة (للإدارة) - مع Pagination
     */
    public function getAllOrders(array $filters = []): LengthAwarePaginator
    {
        return CustomOrder::with(['items', 'user', 'driver'])
            ->when($filters['status'] ?? null, fn($q, $status) => $q->byStatus($status))
            ->when($filters['user_id'] ?? null, fn($q, $userId) => $q->where('user_id', $userId))
            ->when($filters['driver_id'] ?? null, fn($q, $driverId) => $q->where('driver_id', $driverId))
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * جلب جميع الطلبات الخاصة (Collection للدمج)
     */
    public function getAllOrdersCollection(array $filters = [])
    {
        return CustomOrder::with(['items', 'user', 'driver'])
            ->when($filters['status'] ?? null, fn($q, $status) => $q->byStatus($status))
            ->when($filters['user_id'] ?? null, fn($q, $userId) => $q->where('user_id', $userId))
            ->when($filters['driver_id'] ?? null, fn($q, $driverId) => $q->where('driver_id', $driverId))
            ->latest()
            ->get();
    }

    /* ═══════════════════════════════════════════════════════════════════
     * وظائف السائق - Driver Functions
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * جلب الطلبات المتاحة للسائقين (المعلقة) - مع Pagination
     */
    public function getAvailableOrdersForDrivers(array $filters = []): LengthAwarePaginator
    {
        return CustomOrder::availableForDrivers()
            ->with(['items', 'user'])
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * جلب الطلبات المتاحة للسائقين (Collection للدمج)
     */
    public function getAvailableOrdersCollection()
    {
        return CustomOrder::availableForDrivers()
            ->with(['items', 'user'])
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
            $availableOrders = $this->geofencingService->getAvailableCustomOrdersForDriver($driver);
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

        return CustomOrder::forDriver($driver->id)
            ->with(['items', 'user'])
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

        return CustomOrder::forDriver($driver->id)
            ->with(['items', 'user'])
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
        $myOrders = CustomOrder::forDriver($driver->id)
            ->with(['items', 'user'])
            ->latest()
            ->get();

        // الطلبات المتاحة في منطقة السائق الجغرافية
        $availableOrders = $this->geofencingService->getAvailableCustomOrdersForDriver($driver);

        return [
            'shipping' => $myOrders->where('status', CustomOrder::STATUS_SHIPPING)->values(),
            'delivered' => $myOrders->where('status', CustomOrder::STATUS_DELIVERED)->values(),
            'cancelled' => $myOrders->where('status', CustomOrder::STATUS_CANCELLED)->values(),
            'available' => $availableOrders->values(),
        ];
    }

    /**
     * جلب تفاصيل طلب للسائق الحالي (عام)
     */
    public function getOrderDetailsForDriver(int $orderId): CustomOrder
    {
        return $this->getDriverOrder($orderId);
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

        // التحقق من رصيد المحفظة
        if (!$driver->hasEnoughBalanceForDelivery()) {
            $this->throwExceptionJson('رصيد محفظتك غير كافٍ لقبول هذا الطلب', 400);
        }

        // التحقق من عدد الطلبات النشطة حسب نوع الطلب (فوري/مجدول)
        if ($order->is_immediate) {
            if (!$driver->canAcceptImmediateCustomOrder()) {
                $this->throwExceptionJson('لديك طلب فوري قيد التوصيل بالفعل', 400);
            }
        } else {
            if (!$driver->canAcceptScheduledOrder()) {
                $this->throwExceptionJson('لقد وصلت للحد الأقصى من الطلبات المجدولة (3 طلبات)', 400);
            }
        }

        // محاولة القبول مع التعامل مع Race Condition
        $updated = CustomOrder::where('id', $orderId)
            // ->whereNull('driver_id')
            ->where('status', CustomOrder::STATUS_PENDING)
            ->update([
                'driver_id' => $driver->id,
                'driver_assigned_at' => now(),
                'status' => CustomOrder::STATUS_SHIPPING,
            ]);

        if (!$updated) {
            $this->throwExceptionJson('تم قبول هذا الطلب من سائق آخر', 400);
        }

        $order = $order->fresh(['items', 'user']);

        // Notify user that driver accepted the order
        $this->notificationService->notifyUserCustomOrderAccepted($order);

        return $order;
    }

    /**
     * السائق يؤكد تسليم الطلب
     * (shipping → delivered)
     * 
     * - يتم خصم نسبة الربح من محفظة السائق
     */
    public function confirmDeliveryByDriver(int $orderId)
    {
        $driver = Auth::guard('driver')->user();
        $order = $this->getDriverOrder($orderId);

        if ($order->status !== CustomOrder::STATUS_SHIPPING) {
            $this->throwExceptionJson('لا يمكن تأكيد التسليم في هذه الحالة', 400);
        }

        return DB::transaction(function () use ($order, $driver) {
            $order->markAsDelivered();

            // Process driver delivery profit (deduct from wallet)
            $this->adminProfitService->processDriverDeliveryProfit(
                $driver,
                AdminProfit::ORDER_TYPE_CUSTOM,
                $order->id,
                (float) $order->delivery_fee
            );

            $order = $order->fresh(['items', 'user']);

            // Notify user that order was delivered
            $this->notificationService->notifyUserCustomOrderDelivered($order);

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
            if ($order->is_immediate) {
                $this->throwExceptionJson('لا يمكن إلغاء الطلبات الفورية - فقط الإدارة تستطيع ذلك', 400);
            }
            $this->throwExceptionJson('لا يمكن إلغاء هذا الطلب في حالته الحالية', 400);
        }

        // إلغاء الطلب
        $order->markAsCancelled($reason);
        $order->refresh();

        // Notify user about cancellation
        $this->notificationService->notifyUserCustomOrderCancelled($order, 'driver');

        // Notify admins about driver cancellation
        $this->notificationService->notifyAdminsDriverCancelledOrder($order, $reason);

        // تجهيز البيانات للإدارة
        $adminNotificationData = [
            'order' => [
                'id' => $order->id,
                'type' => 'custom_order',
                'status' => $order->status,
                'delivery_fee' => $order->delivery_fee,
                'delivery_address' => $order->delivery_address,
                'scheduled_at' => $order->scheduled_at?->toDateTimeString(),
                'items' => $order->items->map(fn($item) => [
                    'description' => $item->description,
                    'pickup_address' => $item->pickup_address,
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
            'order' => $order->fresh(['items', 'user']),
            'admin_notification' => $adminNotificationData,
        ];
    }

    // ⚠️ ملاحظة: السائق لا يمكنه إلغاء الطلب الفوري بعد القبول
    // الإلغاء فقط من الإدارة عبر cancelOrderByAdmin()

    /**
     * إلغاء طلب من الإدارة (يعمل في أي حالة ما عدا delivered/cancelled)
     */
    public function cancelOrderByAdmin(int $orderId, string $reason): CustomOrder
    {
        $order = CustomOrder::find($orderId);

        if (!$order) {
            $this->throwExceptionJson('الطلب غير موجود', 404);
        }

        if (!$order->can_admin_cancel) {
            $this->throwExceptionJson('لا يمكن إلغاء هذا الطلب', 400);
        }

        $order->markAsCancelled($reason);

        // Notify user about admin cancellation
        $this->notificationService->notifyUserCustomOrderCancelled($order, 'admin');

        // Notify driver if order was assigned
        if ($order->driver_id) {
            $this->notificationService->notifyDriverOrderCancelledByUser($order->driver, $order);
        }

        return $order->fresh(['items', 'user', 'driver']);
    }

    /* ═══════════════════════════════════════════════════════════════════
     * وظائف النظام - System Functions
     * ═══════════════════════════════════════════════════════════════════ */

    // ملاحظة: لا نلغي الطلبات تلقائياً عند انتهاء الصلاحية
    // المستخدم يضغط "تأكيد الطلب" مرة أخرى لتجديد فترة الانتظار

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
     * جلب طلب للسائق الحالي
     */
    public function getDriverOrderDetails(int $orderId)
    {
        $driver = Auth::guard('driver')->user();

        $order = CustomOrder::with(['items', 'user'])
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
