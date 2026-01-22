<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomOrder\StoreCustomOrderRequest;
use App\Http\Requests\CustomOrder\UpdateCustomOrderRequest;
use App\Http\Resources\CustomOrderResource;
use App\Services\CustomOrder\CustomOrderService;
use Illuminate\Http\Request;

class CustomOrderController extends Controller
{
    protected CustomOrderService $customOrderService;

    public function __construct(CustomOrderService $customOrderService)
    {
        $this->customOrderService = $customOrderService;
    }

    /* ==========================================
     * APIs for users (customers)
     * ========================================== */

    /**
     * get specific custom order details
     *
     * GET /custom-orders
     */
    public function index(Request $request)
    {
        $orders = $this->customOrderService->getUserOrders([
            'status' => $request->query('status'),
            'per_page' => $request->query('per_page', 15),
        ]);

        return $this->paginate(
            $orders->setCollection($orders->getCollection()->map(fn($o) => new CustomOrderResource($o))),
            'تم جلب الطلبات بنجاح'
        );
    }

    /**
     * Get specific custom order details
     *
     * GET /custom-orders/{id}
     */
    public function show(int $id)
    {
        $order = $this->customOrderService->getOrderDetails($id);

        return $this->success(
            new CustomOrderResource($order),
            'تم جلب تفاصيل الطلب بنجاح'
        );
    }

    /**
     * Create new custom order (draft)
     *
     * POST /custom-orders
     */
    public function store(StoreCustomOrderRequest $request)
    {
        $order = $this->customOrderService->createOrder($request->validated());

        return $this->success(
            new CustomOrderResource($order),
            'تم إنشاء الطلب بنجاح',
            201
        );
    }

    /**
     * Cancel order
     *
     * POST /custom-orders/{id}/cancel
     */
    public function cancel(Request $request, int $id)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $order = $this->customOrderService->cancelOrder($id, $validated['reason'] ?? null);

        return $this->success(
            new CustomOrderResource($order),
            'تم إلغاء الطلب بنجاح'
        );
    }

    /**
     * Retry delivery after cancellation (for user)
     *
     * POST /custom-orders/{id}/retry-delivery
     */
    public function retryDelivery(int $id)
    {
        $order = $this->customOrderService->retryDelivery($id);

        return $this->success(
            new CustomOrderResource($order),
            'تم إعادة إرسال الطلب للسائقين'
        );
    }

    /**
     * Resend order to drivers (renew waiting period)
     *
     * POST /custom-orders/{id}/resend
     */
    public function resendToDrivers(int $id)
    {
        $order = $this->customOrderService->resendToDrivers($id);

        return $this->success(
            new CustomOrderResource($order),
            'تم إعادة إرسال الطلب للسائقين'
        );
    }

    /**
     * Calculate delivery fee (preview before creation)
     *
     * POST /custom-orders/calculate-fee
     */
    public function calculateFee(Request $request)
    {
        $validated = $request->validate([
            'distance_km' => 'required|numeric|min:0.1|max:100',
        ]);

        $fee = $this->customOrderService->calculateDeliveryFee($validated['distance_km']);

        return $this->success([
            'distance_km' => (float) $validated['distance_km'],
            'delivery_fee' => $fee,
        ], 'تم حساب سعر التوصيل');
    }

    /* ==========================================
     * APIs for drivers
     * ========================================== */

    /**
     * Get available custom orders for delivery
     *
     * GET /driver/custom-orders/available
     */
    public function availableOrders(Request $request)
    {
        $orders = $this->customOrderService->getAvailableOrdersForDrivers([
            'per_page' => $request->query('per_page', 15),
        ]);

        return $this->paginate(
            $orders->setCollection($orders->getCollection()->map(fn($o) => new CustomOrderResource($o))),
            'الطلبات المتاحة للتوصيل'
        );
    }

    /**
     * Get driver's custom orders
     *
     * GET /driver/custom-orders
     */
    public function driverOrders(Request $request)
    {
        $orders = $this->customOrderService->getDriverOrders([
            'status' => $request->query('status'),
            'per_page' => $request->query('per_page', 15),
        ]);

        return $this->paginate(
            $orders->setCollection($orders->getCollection()->map(fn($o) => new CustomOrderResource($o))),
            'طلباتك الخاصة'
        );
    }

    /**
     * Driver accepts a custom order and starts delivery
     *
     * POST /driver/custom-orders/{id}/accept
     */
    public function acceptOrder(int $id)
    {
        $order = $this->customOrderService->acceptOrderByDriver($id);

        return $this->success(
            new CustomOrderResource($order),
            'تم قبول الطلب وبدء التوصيل'
        );
    }

    /**
     * Driver confirms delivery
     *
     * POST /driver/custom-orders/{id}/deliver
     */
    public function deliverOrder(int $id)
    {
        $order = $this->customOrderService->confirmDeliveryByDriver($id);

        return $this->success(
            new CustomOrderResource($order),
            'تم تسليم الطلب بنجاح'
        );
    }

    /**
     * Driver cancels a scheduled order (immediate orders cannot be cancelled)
     * 
     * POST /driver/custom-orders/{id}/cancel
     * 
     * ⚠️ Order, user details and cancellation reason are sent to admin
     */
    public function driverCancelScheduledOrder(Request $request, int $id)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $result = $this->customOrderService->cancelScheduledOrderByDriver($id, $validated['reason']);

        return $this->success([
            'order' => new CustomOrderResource($result['order']),
            'notification_sent_to_admin' => true,
            'message_to_driver' => 'تم إلغاء الطلب وإرسال البيانات للإدارة',
        ], 'تم إلغاء الطلب المجدول بنجاح');
    }

    /* ==========================================
     * Admin APIs
     * ========================================== */

    /**
     * Get all custom orders (admin)
     *
     * GET /admin/custom-orders
     */
    public function allOrders(Request $request)
    {
        $orders = $this->customOrderService->getAllOrders([
            'status' => $request->query('status'),
            'user_id' => $request->query('user_id'),
            'driver_id' => $request->query('driver_id'),
            'per_page' => $request->query('per_page', 15),
        ]);

        return $this->paginate(
            $orders->setCollection($orders->getCollection()->map(fn($o) => new CustomOrderResource($o))),
            'تم جلب الطلبات بنجاح'
        );
    }

    /**
     * Cancel a custom order by admin
     *
     * POST /admin/custom-orders/{id}/cancel
     */
    public function adminCancelOrder(Request $request, int $id)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $order = $this->customOrderService->cancelOrderByAdmin($id, $validated['reason']);

        return $this->success(
            new CustomOrderResource($order),
            'تم إلغاء الطلب بنجاح'
        );
    }

    /* ==========================================
     * Admin APIs - entity-specific orders
     * ========================================== */

    /**
     * Get orders of a specific user (admin)
     *
     * GET /admin/users/{id}/custom-orders
     */
    public function userOrdersForAdmin(Request $request, int $id)
    {
        $orders = $this->customOrderService->getAllOrders([
            'status' => $request->query('status'),
            'user_id' => $id,
            'per_page' => $request->query('per_page', 15),
        ]);

        return $this->paginate(
            $orders->setCollection($orders->getCollection()->map(fn($o) => new CustomOrderResource($o))),
            'تم جلب طلبات المستخدم الخاصة بنجاح'
        );
    }

    /**
     * Get orders of a specific driver (admin)
     *
     * GET /admin/drivers/{id}/custom-orders
     */
    public function driverOrdersForAdmin(Request $request, int $id)
    {
        $orders = $this->customOrderService->getAllOrders([
            'status' => $request->query('status'),
            'driver_id' => $id,
            'per_page' => $request->query('per_page', 15),
        ]);

        return $this->paginate(
            $orders->setCollection($orders->getCollection()->map(fn($o) => new CustomOrderResource($o))),
            'تم جلب طلبات السائق الخاصة بنجاح'
        );
    }
}
