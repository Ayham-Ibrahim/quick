<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\ReorderRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\StoreOrderResource;
use App\Services\Geofencing\GeofencingService;
use App\Services\Order\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    protected OrderService $orderService;
    protected GeofencingService $geofencingService;

    public function __construct(OrderService $orderService, GeofencingService $geofencingService)
    {
        $this->orderService = $orderService;
        $this->geofencingService = $geofencingService;
    }

    /* ==========================================
     * APIs for users (customers)
     * ========================================== */

    /**
     * Get current user's orders
     *
     * GET /orders
     */
    public function index(Request $request)
    {
        $orders = $this->orderService->getUserOrders([
            'status' => $request->query('status'),
            'per_page' => $request->query('per_page', 15),
        ]);

        return $this->paginate(
            $orders->setCollection($orders->getCollection()->map(fn($o) => new OrderResource($o))),
            'تم جلب الطلبات بنجاح'
        );
    }

    /**
     * Get specific order details
     *
     * GET /orders/{id}
     */
    public function show(int $id)
    {
        $order = $this->orderService->getOrderDetails($id);

        return $this->success(
            new OrderResource($order),
            'تم جلب تفاصيل الطلب بنجاح'
        );
    }

    /**
     * Cancel order
     *
     * POST /orders/{id}/cancel
     */
    public function cancel(Request $request, int $id)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $order = $this->orderService->cancelOrder($id, $validated['reason'] ?? null);

        return $this->success(
            new OrderResource($order),
            'تم إلغاء الطلب بنجاح'
        );
    }

    /**
     * Retry delivery after cancellation
     *
     * POST /orders/{id}/retry-delivery
     */
    public function retryDelivery(int $id)
    {
        $order = $this->orderService->retryDelivery($id);

        return $this->success(
            new OrderResource($order),
            'تم إعادة إرسال الطلب للسائقين'
        );
    }

    /**
     * Resend order to drivers (renew waiting period)
     *
     * POST /orders/{id}/resend
     */
    public function resendToDrivers(int $id)
    {
        $order = $this->orderService->resendToDrivers($id);

        return $this->success(
            new OrderResource($order),
            'تم إعادة إرسال الطلب للسائقين'
        );
    }

    /**
     * Get current driver location for a specific order (for order owner)
     *
     * GET /orders/{id}/driver-location
     */
    public function driverLocation(int $id)
    {
        $location = $this->orderService->getDriverLocationForOrder($id);

        return $this->success($location, 'تم جلب موقع السائق');
    }

    /**
     * Reorder a previous order
     * 
     * POST /orders/{id}/reorder
     * 
     * - Only for delivered orders
     * - Current product prices are used
     * - A completely new order is created
     */
    public function reorder(ReorderRequest $request, int $id)
    {
        $newOrder = $this->orderService->reorderOrder($id, $request->validated());

        $response = [
            'new_order' => new OrderResource($newOrder),
        ];

        // Add notice about unavailable items
        if (isset($newOrder->unavailable_items_notice)) {
            $response['unavailable_items_notice'] = $newOrder->unavailable_items_notice;
            $response['notice_message'] = 'بعض المنتجات لم تكن متاحة أو الكمية المطلوبة غير متوفرة';
        }

        return $this->success($response, 'تم إنشاء الطلب الجديد بنجاح', 201);
    }
        
    

    /* ==========================================
     * APIs for drivers
     * ========================================== */

    /**
     * Get available orders for delivery
     *
     * GET /driver/available-orders
     */
    public function availableOrders(Request $request)
    {
        $orders = $this->orderService->getAvailableOrdersForDelivery([
            'per_page' => $request->query('per_page', 15),
        ]);

        return $this->paginate(
            $orders->setCollection($orders->getCollection()->map(fn($o) => new OrderResource($o))),
            'الطلبات المتاحة للتوصيل'
        );
    }

    /**
     * show Order Detail For Driver
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function showOrderDetailForDriver(int $id)
    {
        $order = $this->orderService->getOrderDetailsForDriver($id);
        return $this->success(new OrderResource($order), 'تفاصيل الطلب');
    }

    /**
     * Get driver's orders (paginated, optionally filtered by status)
     *
     * GET /driver/orders
     */
    public function driverOrders(Request $request)
    {
        $orders = $this->orderService->getDriverOrders([
            'status' => $request->query('status'),
            'per_page' => $request->query('per_page', 15),
        ]);

        return $this->paginate(
            $orders->setCollection($orders->getCollection()->map(fn($o) => new OrderResource($o))),
            'طلباتك'
        );
    }

    /**
     * Get driver's orders grouped by status
     * Includes: shipping, delivered, cancelled, and available orders in driver's area
     *
     * GET /driver/orders/grouped
     */
    public function driverOrdersGrouped()
    {
        $grouped = $this->orderService->getDriverOrdersGrouped();

        return $this->success([
            'shipping' => OrderResource::collection($grouped['shipping']),
            'delivered' => OrderResource::collection($grouped['delivered']),
            'cancelled' => OrderResource::collection($grouped['cancelled']),
            'available' => OrderResource::collection($grouped['available']),
            'counts' => [
                'shipping' => $grouped['shipping']->count(),
                'delivered' => $grouped['delivered']->count(),
                'cancelled' => $grouped['cancelled']->count(),
                'available' => $grouped['available']->count(),
            ],
        ], 'طلباتك مجمّعة حسب الحالة');
    }

    /**
     * Driver accepts an order
     *
     * POST /driver/orders/{id}/accept
     */
    public function acceptOrder(int $id)
    {
        $order = $this->orderService->acceptOrderByDriver($id);
        $driver = Auth::guard('driver')->user();

        // Calculate execution route (stores ordered from nearest to farthest)
        $executionRoute = $this->geofencingService->getOrderExecutionRoute($order, $driver);

        return $this->success([
            'order' => new OrderResource($order),
            'execution_route' => $executionRoute,
        ], 'تم قبول الطلب بنجاح');
    }

    /**
     * Driver confirms delivery
     *
     * POST /driver/orders/{id}/deliver
     */
    public function deliverOrder(int $id)
    {
        $order = $this->orderService->confirmDeliveryByDriver($id);

        return $this->success(
            new OrderResource($order),
            'تم تسليم الطلب بنجاح'
        );
    }

    /**
     * Driver cancels a scheduled order (immediate orders cannot be cancelled)
     * 
     * POST /driver/orders/{id}/cancel
     * 
     * ⚠️ Order, user details and cancellation reason are sent to admin
     */
    public function driverCancelScheduledOrder(Request $request, int $id)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $result = $this->orderService->cancelScheduledOrderByDriver($id, $validated['reason']);

        return $this->success([
            'order' => new OrderResource($result['order']),
            'notification_sent_to_admin' => true,
            'message_to_driver' => 'تم إلغاء الطلب وإرسال البيانات للإدارة',
        ], 'تم إلغاء الطلب المجدول بنجاح');
    }

    /* ==========================================
     * Admin APIs
     * ========================================== */

    /**
     * Get all orders (admin)
     *
     * GET /admin/orders
     */
    public function allOrders(Request $request)
    {
        $orders = $this->orderService->getAllOrders([
            'status' => $request->query('status'),
            'user_id' => $request->query('user_id'),
            'driver_id' => $request->query('driver_id'),
            'per_page' => $request->query('per_page', 15),
        ]);

        return $this->paginate(
            $orders->setCollection($orders->getCollection()->map(fn($o) => new OrderResource($o))),
            'تم جلب الطلبات بنجاح'
        );
    }

    /**
     * Cancel order by admin (works in any state except delivered/cancelled)
     *
     * POST /admin/orders/{id}/cancel
     */
    public function adminCancelOrder(Request $request, int $id)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $order = $this->orderService->cancelOrderByAdmin($id, $validated['reason']);

        return $this->success(
            new OrderResource($order),
            'تم إلغاء الطلب بنجاح'
        );
    }

    /* ==========================================
     * Admin APIs - entity-specific orders
     * ========================================== */

    /**
     * Get orders of a specific user (admin)
     *
     * GET /admin/users/{id}/orders
     */
    public function userOrdersForAdmin(Request $request, int $id)
    {
        $orders = $this->orderService->getAllOrders([
            'status' => $request->query('status'),
            'user_id' => $id,
            'per_page' => $request->query('per_page', 15),
        ]);

        return $this->paginate(
            $orders->setCollection($orders->getCollection()->map(fn($o) => new OrderResource($o))),
            'تم جلب طلبات المستخدم بنجاح'
        );
    }

    /**
     * Get orders of a specific driver (admin)
     *
     * GET /admin/drivers/{id}/orders
     */
    public function driverOrdersForAdmin(Request $request, int $id)
    {
        $orders = $this->orderService->getAllOrders([
            'status' => $request->query('status'),
            'driver_id' => $id,
            'per_page' => $request->query('per_page', 15),
        ]);

        return $this->paginate(
            $orders->setCollection($orders->getCollection()->map(fn($o) => new OrderResource($o))),
            'تم جلب طلبات السائق بنجاح'
        );
    }

    /**
     * Get orders of a specific store (admin)
     *
     * GET /admin/stores/{id}/orders
     */
    public function storeOrdersForAdmin(Request $request, int $id)
    {
        $orders = $this->orderService->getStoreOrders($id, [
            'status' => $request->query('status'),
            'per_page' => $request->query('per_page', 15),
        ]);

        return $this->paginate(
            $orders->setCollection($orders->getCollection()->map(fn($o) => new OrderResource($o))),
            'تم جلب طلبات المتجر بنجاح'
        );
    }

    /* ==========================================
     * APIs for Store Owners (تطبيق المتاجر)
     * ========================================== */

    /**
     * Get store's active orders (pending + shipping)
     * 
     * GET /store/orders
     * 
     * يعرض فقط المنتجات التابعة للمتجر في كل طلب
     */
    public function storeOrders(Request $request)
    {
        $store = Auth::guard('store')->user();

        $orders = $this->orderService->getStoreOwnerActiveOrders($store->id, [
            'status' => $request->query('status'),
            'per_page' => $request->query('per_page', 15),
        ]);

        return $this->paginate(
            $orders->setCollection($orders->getCollection()->map(fn($o) => new StoreOrderResource($o))),
            'تم جلب الطلبات بنجاح'
        );
    }

    /**
     * Get store's orders history (delivered + cancelled) with financial stats
     * 
     * GET /store/orders/history
     * 
     * يعرض سجل الطلبات المكتملة والملغية مع إحصائيات مالية:
     * - إجمالي رصيد الطلبات
     * - نسبة الإدارة
     * - رصيد طلبات المتجر
     */
    public function storeOrdersHistory(Request $request)
    {
        $store = Auth::guard('store')->user();

        $result = $this->orderService->getStoreOwnerOrdersHistory($store->id, [
            'status' => $request->query('status'),
            'per_page' => $request->query('per_page', 15),
        ]);

        $orders = $result['orders'];
        $financialStats = $result['financial_stats'];

        return $this->paginateWithData(
            $orders->setCollection($orders->getCollection()->map(fn($o) => new StoreOrderResource($o))),
            [
                'financialStats' => [
                    'totalOrdersCount' => $financialStats['total_orders_count'],
                    'totalStoreRevenue' => $financialStats['total_store_revenue'],
                    'totalCouponDiscount' => $financialStats['total_coupon_discount'],
                    'adminProfitPercentage' => $financialStats['admin_profit_percentage'],
                    'adminProfitAmount' => $financialStats['admin_profit_amount'],
                    'netStoreBalance' => $financialStats['net_store_balance'],
                ],
            ],
            'تم جلب سجل الطلبات بنجاح'
        );
    }

    /**
     * Get specific order details for store owner
     * 
     * GET /store/orders/{id}
     * 
     * يعرض فقط المنتجات التابعة للمتجر مع:
     * - تفاصيل الأسعار والخصومات
     * - معلومات العميل
     * - معلومات السائق
     * - عنوان التوصيل
     */
    public function storeOrderDetails(int $id)
    {
        $store = Auth::guard('store')->user();

        $order = $this->orderService->getStoreOwnerOrderDetails($store->id, $id);

        return $this->success(
            new StoreOrderResource($order),
            'تم جلب تفاصيل الطلب بنجاح'
        );
    }
}
