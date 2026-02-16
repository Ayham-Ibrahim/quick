<?php

namespace App\Services;

use App\Models\Order;
use App\Models\CustomOrder;
use App\Models\Driver;
use App\Models\Store;
use App\Models\UserManagement\User;
use Illuminate\Support\Facades\Log;

/**
 * Notification Service
 * 
 * Central service for sending push notifications throughout the app.
 * 
 * Notification Recipients:
 * - User: Order updates (created, accepted, delivered, cancelled)
 * - Driver: New available orders, order cancellations
 * - Store: New orders with their products, delivery confirmations
 * - Admin: Important alerts (complaints, issues)
 */
class NotificationService
{
    protected FcmService $fcmService;

    public function __construct(FcmService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /* ═══════════════════════════════════════════════════════════════════
     * User Notifications
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * Notify user when order is created successfully.
     */
    public function notifyUserOrderCreated(Order $order): void
    {
        $this->fcmService->sendToUser(
            $order->user,
            'تم إنشاء طلبك بنجاح ✅',
            'شكراً لتسوقك معنا! طلبك رقم #' . $order->id . ' بانتظار قبول سائق.',
            [
                'type' => 'order_created',
                'order_id' => (string) $order->id,
                'status' => $order->status,
            ]
        );
    }

    /**
     * Notify user when driver accepts the order.
     */
    public function notifyUserOrderAccepted(Order $order): void
    {
        $driverName = $order->driver->driver_name ?? 'السائق';

        $this->fcmService->sendToUser(
            $order->user,
            'تم قبول طلبك 🚗',
            $driverName . ' في طريقه إليك! طلبك رقم #' . $order->id,
            [
                'type' => 'order_accepted',
                'order_id' => (string) $order->id,
                'driver_id' => (string) $order->driver_id,
                'status' => $order->status,
            ]
        );
    }

    /**
     * Notify user when order is delivered.
     */
    public function notifyUserOrderDelivered(Order $order): void
    {
        $this->fcmService->sendToUser(
            $order->user,
            'تم تسليم طلبك 🎉',
            'استمتع بطلبك! شكراً لثقتك بنا. طلب رقم #' . $order->id,
            [
                'type' => 'order_delivered',
                'order_id' => (string) $order->id,
                'status' => $order->status,
            ]
        );
    }

    /**
     * Notify user when order is cancelled.
     */
    public function notifyUserOrderCancelled(Order $order, string $cancelledBy = 'user'): void
    {
        $messages = [
            'user' => 'تم إلغاء طلبك بنجاح.',
            'driver' => 'تم إلغاء طلبك من قبل السائق. يمكنك إعادة المحاولة.',
            'admin' => 'تم إلغاء طلبك من قبل الإدارة.',
            'system' => 'تم إلغاء طلبك لعدم توفر سائق. يمكنك إعادة المحاولة.',
        ];

        $this->fcmService->sendToUser(
            $order->user,
            'تم إلغاء الطلب ❌',
            $messages[$cancelledBy] ?? $messages['system'],
            [
                'type' => 'order_cancelled',
                'order_id' => (string) $order->id,
                'cancelled_by' => $cancelledBy,
                'status' => $order->status,
            ]
        );
    }

    /* ═══════════════════════════════════════════════════════════════════
     * User Notifications - Custom Orders
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * Notify user when custom order is created.
     */
    public function notifyUserCustomOrderCreated(CustomOrder $order): void
    {
        $this->fcmService->sendToUser(
            $order->user,
            'تم إنشاء طلبك الخاص ✅',
            'طلبك رقم #' . $order->id . ' بانتظار قبول سائق.',
            [
                'type' => 'custom_order_created',
                'order_id' => (string) $order->id,
                'order_type' => 'custom',
                'status' => $order->status,
            ]
        );
    }

    /**
     * Notify user when driver accepts custom order.
     */
    public function notifyUserCustomOrderAccepted(CustomOrder $order): void
    {
        $driverName = $order->driver->driver_name ?? 'السائق';

        $this->fcmService->sendToUser(
            $order->user,
            'تم قبول طلبك الخاص 🚗',
            $driverName . ' في طريقه لتنفيذ طلبك!',
            [
                'type' => 'custom_order_accepted',
                'order_id' => (string) $order->id,
                'order_type' => 'custom',
                'driver_id' => (string) $order->driver_id,
                'status' => $order->status,
            ]
        );
    }

    /**
     * Notify user when custom order is delivered.
     */
    public function notifyUserCustomOrderDelivered(CustomOrder $order): void
    {
        $this->fcmService->sendToUser(
            $order->user,
            'تم تسليم طلبك الخاص 🎉',
            'تم تنفيذ طلبك بنجاح! شكراً لثقتك بنا.',
            [
                'type' => 'custom_order_delivered',
                'order_id' => (string) $order->id,
                'order_type' => 'custom',
                'status' => $order->status,
            ]
        );
    }

    /**
     * Notify user when custom order is cancelled.
     */
    public function notifyUserCustomOrderCancelled(CustomOrder $order, string $cancelledBy = 'user'): void
    {
        $messages = [
            'user' => 'تم إلغاء طلبك الخاص بنجاح.',
            'driver' => 'تم إلغاء طلبك من قبل السائق. يمكنك إعادة المحاولة.',
            'admin' => 'تم إلغاء طلبك من قبل الإدارة.',
            'system' => 'تم إلغاء طلبك لعدم توفر سائق.',
        ];

        $this->fcmService->sendToUser(
            $order->user,
            'تم إلغاء الطلب ❌',
            $messages[$cancelledBy] ?? $messages['system'],
            [
                'type' => 'custom_order_cancelled',
                'order_id' => (string) $order->id,
                'order_type' => 'custom',
                'cancelled_by' => $cancelledBy,
                'status' => $order->status,
            ]
        );
    }

    /* ═══════════════════════════════════════════════════════════════════
     * Driver Notifications
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * Notify a specific driver about a new available order.
     */
    public function notifyDriverNewOrder(Driver $driver, Order $order): void
    {
        $this->fcmService->sendToDriver(
            $driver,
            'طلب جديد متاح 📦',
            'طلب جديد بقيمة ' . number_format((float) $order->total, 2) . ' ر.س في انتظارك!',
            [
                'type' => 'new_order_available',
                'order_id' => (string) $order->id,
                'order_type' => 'regular',
                'total' => (string) $order->total,
                'delivery_fee' => (string) $order->delivery_fee,
            ]
        );
    }

    /**
     * Notify a specific driver about a new custom order.
     */
    public function notifyDriverNewCustomOrder(Driver $driver, CustomOrder $order): void
    {
        $this->fcmService->sendToDriver(
            $driver,
            'طلب خاص جديد 📦',
            'طلب توصيل جديد برسوم ' . number_format((float) $order->delivery_fee, 2) . ' ر.س',
            [
                'type' => 'new_order_available',
                'order_id' => (string) $order->id,
                'order_type' => 'custom',
                'delivery_fee' => (string) $order->delivery_fee,
            ]
        );
    }

    /**
     * Notify multiple drivers about a new order.
     */
    public function notifyDriversNewOrder($drivers, Order $order): int
    {
        $successCount = 0;
        $total = count($drivers);

        foreach ($drivers as $driver) {
            try {
                $this->notifyDriverNewOrder($driver, $order);
                $successCount++;
            } catch (\Exception $e) {
                Log::warning("Failed to notify driver #{$driver->id}: " . $e->getMessage());
            }
        }

        Log::info("notifyDriversNewOrder: attempted to notify drivers", [
            'order_id' => $order->id,
            'eligible_drivers' => $total,
            'successful_sends' => $successCount,
        ]);

        return $successCount;
    }

    /**
     * Notify multiple drivers about a new custom order.
     */
    public function notifyDriversNewCustomOrder($drivers, CustomOrder $order): int
    {
        $successCount = 0;

        foreach ($drivers as $driver) {
            try {
                $this->notifyDriverNewCustomOrder($driver, $order);
                $successCount++;
            } catch (\Exception $e) {
                Log::warning("Failed to notify driver #{$driver->id}: " . $e->getMessage());
            }
        }

        return $successCount;
    }

    /**
     * Notify driver when user cancels their order.
     */
    public function notifyDriverOrderCancelledByUser(Driver $driver, Order|CustomOrder $order): void
    {
        $orderType = $order instanceof CustomOrder ? 'custom' : 'regular';
        
        $this->fcmService->sendToDriver(
            $driver,
            'تم إلغاء الطلب ❌',
            'قام العميل بإلغاء الطلب رقم #' . $order->id,
            [
                'type' => 'order_cancelled_by_user',
                'order_id' => (string) $order->id,
                'order_type' => $orderType,
            ]
        );
    }

    /* ═══════════════════════════════════════════════════════════════════
     * Store Notifications
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * Notify store about a new order containing their products.
     */
    public function notifyStoreNewOrder(Store $store, Order $order, int $itemsCount = 0): void
    {
        $this->fcmService->sendToStore(
            $store,
            'طلب جديد 🛒',
            'لديك طلب جديد يحتوي على ' . $itemsCount . ' منتج(ات). طلب رقم #' . $order->id,
            [
                'type' => 'new_order',
                'order_id' => (string) $order->id,
                'items_count' => (string) $itemsCount,
            ]
        );
    }

    /**
     * Notify store when a product submitted by the store is approved by admin.
     */
    public function notifyStoreProductApproved(Store $store, \App\Models\Product $product): void
    {
        $this->fcmService->sendToStore(
            $store,
            'تم قبول منتجك ✅',
            'تمت الموافقة على منتجك "' . $product->name . '" وهو الآن متاح للعرض والبيع.',
            [
                'type' => 'product_approved',
                'product_id' => (string) $product->id,
                'product_name' => (string) $product->name,
            ]
        );
    }

    /**
     * Notify stores about a new order (for all stores in the order).
     */
    public function notifyStoresNewOrder(Order $order): void
    {
        // Group items by store
        $storeItems = $order->items->groupBy('store_id');

        foreach ($storeItems as $storeId => $items) {
            $store = Store::find($storeId);
            if ($store) {
                $this->notifyStoreNewOrder($store, $order, $items->count());
            }
        }
    }

    /**
     * Notify store when order is delivered.
     */
    public function notifyStoreOrderDelivered(Store $store, Order $order): void
    {
        $this->fcmService->sendToStore(
            $store,
            'تم تسليم الطلب ✅',
            'تم تسليم الطلب رقم #' . $order->id . ' للعميل بنجاح.',
            [
                'type' => 'order_delivered',
                'order_id' => (string) $order->id,
            ]
        );
    }

    /**
     * Notify all stores in an order when it's delivered.
     */
    public function notifyStoresOrderDelivered(Order $order): void
    {
        $storeIds = $order->items->pluck('store_id')->unique();

        foreach ($storeIds as $storeId) {
            $store = Store::find($storeId);
            if ($store) {
                $this->notifyStoreOrderDelivered($store, $order);
            }
        }
    }

    /* ═══════════════════════════════════════════════════════════════════
     * Admin Notifications
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * Notify admins about an important event.
     */
    public function notifyAdmins(string $title, string $body, array $data = []): int
    {
        $admins = User::where('is_admin', true)->get();
        $successCount = 0;

        foreach ($admins as $admin) {
            try {
                $this->fcmService->sendToUser($admin, $title, $body, $data);
                $successCount++;
            } catch (\Exception $e) {
                Log::warning("Failed to notify admin #{$admin->id}: " . $e->getMessage());
            }
        }

        return $successCount;
    }

    /**
     * Notify admins about driver cancelling an order.
     */
    public function notifyAdminsDriverCancelledOrder(Order|CustomOrder $order, string $reason): void
    {
        $orderType = $order instanceof CustomOrder ? 'خاص' : 'عادي';
        
        $this->notifyAdmins(
            'إلغاء طلب من السائق ⚠️',
            'قام السائق ' . ($order->driver->driver_name ?? 'غير معروف') . ' بإلغاء طلب ' . $orderType . ' رقم #' . $order->id,
            [
                'type' => 'driver_cancelled_order',
                'order_id' => (string) $order->id,
                'order_type' => $order instanceof CustomOrder ? 'custom' : 'regular',
                'driver_id' => (string) $order->driver_id,
                'reason' => $reason,
            ]
        );
    }

    /**
     * Notify admins about a new complaint.
     */
    public function notifyAdminsNewComplaint(int $complaintId, string $subject): void
    {
        $this->notifyAdmins(
            'شكوى جديدة 📝',
            'تم استلام شكوى جديدة: ' . $subject,
            [
                'type' => 'new_complaint',
                'complaint_id' => (string) $complaintId,
            ]
        );
    }
}
