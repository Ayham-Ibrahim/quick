<?php

namespace App\Http\Controllers\DiscountManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\CouponRequests\StoreCouponRequest;
use App\Http\Requests\CouponRequests\UpdateCouponRequest;
use App\Http\Resources\CouponResource;
use App\Models\DiscountManagement\Coupon;
use App\Services\DiscountManagement\CouponService;

class CouponController extends Controller
{

    protected $couponService;

    public function __construct(CouponService $couponService)
    {
        $this->couponService = $couponService;
    }

    /**
     * Retrieve a paginated list of coupons with their related products and stores.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $coupons = Coupon::with([
            'products.store'
        ])
            ->paginate(10);

        return $this->paginate(
            CouponResource::collection($coupons),
            'تم جلب الكوبونات بنجاح'
        );
    }
    /**
     * Display a single coupon with its related products and stores.
     *
     * @param  Coupon  $coupon
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Coupon $coupon)
    {
        $coupon = $coupon->load('products.store');
        return $this->success(new CouponResource($coupon), "تم جلب الكوبون بنجاح");
    }

    /**
     * Apply a coupon to an order and calculate the discount.
     *
     * @param  Coupon     $coupon      The coupon to be applied
     * @param  int        $userId      The ID of the user applying the coupon
     * @param  int        $orderId     The ID of the order
     * @param  float      $orderTotal  The total amount of the order before discount
     * @param  int|null   $productId   Optional product ID to validate coupon applicability
     * @return \Illuminate\Http\JsonResponse
     */
    public function apply(Coupon $coupon, int $userId, int $orderId, float $orderTotal, ?int $productId)
    {
        $result = $this->couponService->apply(
            $coupon,
            $userId,
            $orderId,
            $orderTotal,
            $productId
        );

        return $this->success(
            $result,
            'تم تطبيق الكوبون بنجاح'
        );
    }
    /**
     * Delete a coupon and detach all related products.
     *
     * @param  Coupon  $coupon
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Coupon $coupon)
    {
        $this->couponService->deleteCoupon($coupon);

        return $this->success(
            null,
            'تم حذف الكوبون بنجاح'
        );
    }
    /**
     * Store a new coupon and attach related products.
     *
     * @param  StoreCouponRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreCouponRequest $request)
    {
        $coupon = $this->couponService->storeCoupon($request->validated());
        return $this->success(
            new CouponResource($coupon),
            'تم إنشاء الكوبون بنجاح'
        );
    }
    /**
     * Update an existing coupon and sync its related products.
     *
     * @param  UpdateCouponRequest  $request
     * @param  Coupon               $coupon
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateCouponRequest $request, Coupon $coupon)
    {
        $updatedCoupon = $this->couponService->updateCoupon($coupon, $request->validated());
        return $this->success(
            new CouponResource($updatedCoupon),
            'تم تحديث الكوبون بنجاح'
        );
    }
}
