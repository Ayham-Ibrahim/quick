<?php

namespace App\Http\Controllers;

use App\Http\Requests\Order\ReorderRequest;
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

    /**
     * إعادة محاولة التوصيل بعد الإلغاء
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
     * إعادة إرسال الطلب للسائقين (تجديد فترة الانتظار)
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
     * إعادة طلب طلبية سابقة (Reorder)
     * 
     * POST /orders/{id}/reorder
     * 
     * - فقط للطلبات المسلّمة
     * - يتم جلب الأسعار الحالية للمنتجات
     * - تُنشأ طلبية جديدة كلياً
     */
    public function reorder(ReorderRequest $request, int $id)
    {
        $newOrder = $this->orderService->reorderOrder($id, $request->validated());

        $response = [
            'new_order' => new OrderResource($newOrder),
        ];

        // إضافة تنبيه عن العناصر غير المتاحة
        if (isset($newOrder->unavailable_items_notice)) {
            $response['unavailable_items_notice'] = $newOrder->unavailable_items_notice;
            $response['notice_message'] = 'بعض المنتجات لم تكن متاحة أو الكمية المطلوبة غير متوفرة';
        }

        return $this->success($response, 'تم إنشاء الطلب الجديد بنجاح', 201);
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
     * السائق يؤكد تسليم الطلب
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
     * السائق يلغي طلب مجدول (الطلبات الفورية لا يمكن إلغاؤها)
     * 
     * POST /driver/orders/{id}/cancel
     * 
     * ⚠️ يتم إرسال تفاصيل الطلب والمستخدم وسبب الإلغاء للإدارة
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
     * APIs للإدارة
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
     * إلغاء طلب من الإدارة (يعمل في أي حالة ما عدا delivered/cancelled)
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
}
