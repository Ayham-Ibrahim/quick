<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Services\Order\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /* ==========================================
     * APIs للمستخدم (العميل)
     * ========================================== */

    /**
     * جلب طلبات المستخدم الحالي
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
     * جلب تفاصيل طلب معين
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
     * إلغاء طلب
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

    /* ==========================================
     * APIs للسائق
     * ========================================== */

    /**
     * جلب الطلبات المتاحة للتوصيل
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
     * جلب طلبات السائق
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
     * السائق يقبل طلب
     *
     * POST /driver/orders/{id}/accept
     */
    public function acceptOrder(int $id)
    {
        $order = $this->orderService->acceptOrderByDriver($id);

        return $this->success(
            new OrderResource($order),
            'تم قبول الطلب بنجاح'
        );
    }

    /**
     * السائق يُتم التوصيل
     *
     * POST /driver/orders/{id}/deliver
     */
    public function deliverOrder(int $id)
    {
        $order = $this->orderService->updateOrderStatusByDriver($id, 'delivered');

        return $this->success(
            new OrderResource($order),
            'تم تسليم الطلب بنجاح'
        );
    }

    /* ==========================================
     * APIs للأدمن/المتجر
     * ========================================== */

    /**
     * جلب كل الطلبات (للأدمن)
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
     * تحديث حالة الطلب (للأدمن/المتجر)
     *
     * PUT /admin/orders/{id}/status
     */
    public function updateStatus(Request $request, int $id)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:pending,confirmed,processing,ready,shipped,delivered,cancelled',
        ]);

        $order = $this->orderService->updateOrderStatus($id, $validated['status']);

        return $this->success(
            new OrderResource($order),
            'تم تحديث حالة الطلب بنجاح'
        );
    }

    /**
     * تعيين سائق لطلب (للأدمن/المتجر)
     *
     * POST /admin/orders/{id}/assign-driver
     */
    public function assignDriver(Request $request, int $id)
    {
        $validated = $request->validate([
            'driver_id' => 'required|exists:drivers,id',
        ]);

        $order = $this->orderService->assignDriver($id, $validated['driver_id']);

        return $this->success(
            new OrderResource($order),
            'تم تعيين السائق بنجاح'
        );
    }
}
