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
     * APIs للمستخدم (العميل)
     * ========================================== */

    /**
     * جلب طلبات المستخدم الخاصة
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
     * جلب تفاصيل طلب معين
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
     * إنشاء طلب خاص جديد (مسودة)
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
     * إلغاء طلب
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
     * إعادة محاولة التوصيل بعد الإلغاء (للمستخدم)
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
     * إعادة إرسال الطلب للسائقين (تجديد فترة الانتظار)
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
     * حساب سعر التوصيل (معاينة قبل الإنشاء)
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
     * APIs للسائق
     * ========================================== */

    /**
     * جلب الطلبات الخاصة المتاحة للتوصيل
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
     * جلب طلبات السائق الخاصة
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
     * السائق يقبل طلب خاص ويبدأ التوصيل
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
     * السائق يؤكد تسليم الطلب
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
     * السائق يلغي التوصيل مع سبب
     *
     * POST /driver/custom-orders/{id}/cancel
     */
    public function cancelDelivery(Request $request, int $id)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $order = $this->customOrderService->cancelDeliveryByDriver($id, $validated['reason']);

        return $this->success(
            new CustomOrderResource($order),
            'تم إلغاء التوصيل'
        );
    }
}
