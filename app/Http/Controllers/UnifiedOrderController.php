<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Http\Resources\CustomOrderResource;
use App\Services\Order\OrderService;
use App\Services\CustomOrder\CustomOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Controller موحد لجلب الطلبات العادية والخاصة معاً
 * 
 * يُستخدم عند الحاجة لعرض كل الطلبات في مكان واحد
 */
class UnifiedOrderController extends Controller
{
    protected OrderService $orderService;
    protected CustomOrderService $customOrderService;

    public function __construct(
        OrderService $orderService,
        CustomOrderService $customOrderService
    ) {
        $this->orderService = $orderService;
        $this->customOrderService = $customOrderService;
    }

    /* ==========================================
     * APIs للمستخدم (العميل)
     * ========================================== */

    /**
     * جلب جميع طلبات المستخدم (عادية + خاصة)
     *
     * GET /all-orders
     */
    public function userOrders(Request $request)
    {
        $status = $request->query('status');
        $perPage = $request->query('per_page', 15);

        // جلب الطلبات العادية
        $regularOrders = $this->orderService->getUserOrdersCollection([
            'status' => $status,
        ]);

        // جلب الطلبات الخاصة
        $customOrders = $this->customOrderService->getUserOrdersCollection([
            'status' => $status,
        ]);

        // دمج وترتيب حسب التاريخ
        $allOrders = $this->mergeAndSortOrders($regularOrders, $customOrders);

        // تقسيم لصفحات
        $paginated = $this->paginateCollection($allOrders, $perPage, $request);

        return $this->success([
            'orders' => $paginated->items(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'summary' => [
                'regular_orders_count' => $regularOrders->count(),
                'custom_orders_count' => $customOrders->count(),
                'total_count' => $allOrders->count(),
            ],
        ], 'تم جلب جميع الطلبات بنجاح');
    }

    /* ==========================================
     * APIs للسائق
     * ========================================== */

    /**
     * جلب الطلبات المتاحة للتوصيل (عادية + خاصة)
     *
     * GET /driver/all-orders/available
     */
    public function availableOrdersForDriver(Request $request)
    {
        $perPage = $request->query('per_page', 15);

        // جلب الطلبات العادية المتاحة
        $regularOrders = $this->orderService->getAvailableOrdersCollection();

        // جلب الطلبات الخاصة المتاحة
        $customOrders = $this->customOrderService->getAvailableOrdersCollection();

        // دمج وترتيب حسب التاريخ
        $allOrders = $this->mergeAndSortOrders($regularOrders, $customOrders);

        // تقسيم لصفحات
        $paginated = $this->paginateCollection($allOrders, $perPage, $request);

        return $this->success([
            'orders' => $paginated->items(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'summary' => [
                'regular_orders_count' => $regularOrders->count(),
                'custom_orders_count' => $customOrders->count(),
                'total_available' => $allOrders->count(),
            ],
        ], 'تم جلب الطلبات المتاحة بنجاح');
    }

    /**
     * جلب طلبات السائق الحالي (عادية + خاصة)
     *
     * GET /driver/all-orders/my
     */
    public function driverOrders(Request $request)
    {
        $status = $request->query('status');
        $perPage = $request->query('per_page', 15);

        // جلب طلبات السائق العادية
        $regularOrders = $this->orderService->getDriverOrdersCollection([
            'status' => $status,
        ]);

        // جلب طلبات السائق الخاصة
        $customOrders = $this->customOrderService->getDriverOrdersCollection([
            'status' => $status,
        ]);

        // دمج وترتيب حسب التاريخ
        $allOrders = $this->mergeAndSortOrders($regularOrders, $customOrders);

        // تقسيم لصفحات
        $paginated = $this->paginateCollection($allOrders, $perPage, $request);

        return $this->success([
            'orders' => $paginated->items(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'summary' => [
                'regular_orders_count' => $regularOrders->count(),
                'custom_orders_count' => $customOrders->count(),
                'total_count' => $allOrders->count(),
            ],
        ], 'تم جلب طلباتك بنجاح');
    }

    /* ==========================================
     * APIs للإدارة
     * ========================================== */

    /**
     * جلب جميع الطلبات (للأدمن)
     *
     * GET /admin/all-orders
     */
    public function allOrders(Request $request)
    {
        $status = $request->query('status');
        $userId = $request->query('user_id');
        $driverId = $request->query('driver_id');
        $perPage = $request->query('per_page', 15);

        // جلب الطلبات العادية
        $regularOrders = $this->orderService->getAllOrdersCollection([
            'status' => $status,
            'user_id' => $userId,
            'driver_id' => $driverId,
        ]);

        // جلب الطلبات الخاصة
        $customOrders = $this->customOrderService->getAllOrdersCollection([
            'status' => $status,
            'user_id' => $userId,
            'driver_id' => $driverId,
        ]);

        // دمج وترتيب حسب التاريخ
        $allOrders = $this->mergeAndSortOrders($regularOrders, $customOrders);

        // تقسيم لصفحات
        $paginated = $this->paginateCollection($allOrders, $perPage, $request);

        return $this->success([
            'orders' => $paginated->items(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'summary' => [
                'regular_orders_count' => $regularOrders->count(),
                'custom_orders_count' => $customOrders->count(),
                'total_count' => $allOrders->count(),
            ],
        ], 'تم جلب جميع الطلبات بنجاح');
    }

    /* ==========================================
     * Helper Methods
     * ========================================== */

    /**
     * دمج وترتيب الطلبات حسب تاريخ الإنشاء
     */
    protected function mergeAndSortOrders($regularOrders, $customOrders)
    {
        // تحويل الطلبات لـ Resources مع إضافة نوع الطلب
        $regular = $regularOrders->map(function ($order) {
            $resource = (new OrderResource($order))->toArray(request());
            $resource['order_type'] = 'regular';
            $resource['order_type_text'] = 'طلب عادي';
            return $resource;
        });

        $custom = $customOrders->map(function ($order) {
            $resource = (new CustomOrderResource($order))->toArray(request());
            $resource['order_type'] = 'custom';
            $resource['order_type_text'] = 'طلب خاص';
            return $resource;
        });

        // دمج وترتيب تنازلياً حسب created_at
        return $regular->concat($custom)
            ->sortByDesc('created_at')
            ->values();
    }

    /**
     * تقسيم Collection لصفحات
     */
    protected function paginateCollection($collection, $perPage, $request)
    {
        $page = $request->query('page', 1);
        $offset = ($page - 1) * $perPage;

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $collection->slice($offset, $perPage)->values(),
            $collection->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }
}
