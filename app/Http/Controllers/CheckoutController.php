<?php

namespace App\Http\Controllers;

use App\Http\Requests\Checkout\CheckoutRequest;
use App\Http\Requests\Checkout\ValidateCouponRequest;
use App\Http\Resources\OrderResource;
use App\Services\Checkout\CheckoutService;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    protected CheckoutService $checkoutService;

    public function __construct(CheckoutService $checkoutService)
    {
        $this->checkoutService = $checkoutService;
    }

    /**
     * معاينة الطلب قبل التأكيد
     * يمكن إرسال كوبون للتحقق منه وعرض الخصم
     *
     * GET /checkout/preview?coupon_code=SAVE10
     */
    public function preview(Request $request)
    {
        $couponCode = $request->query('coupon_code');

        $preview = $this->checkoutService->preview($couponCode);

        return $this->success($preview, 'معاينة الطلب');
    }

    /**
     * التحقق من صلاحية الكوبون
     *
     * POST /checkout/validate-coupon
     */
    public function validateCoupon(ValidateCouponRequest $request)
    {
        $result = $this->checkoutService->validateCoupon($request->coupon_code);

        return $this->success($result, $result['message']);
    }

    /**
     * إتمام عملية الشراء
     *
     * POST /checkout
     */
    public function checkout(CheckoutRequest $request)
    {
        $order = $this->checkoutService->checkout($request->validated());

        return $this->success(
            new OrderResource($order),
            'تم إنشاء الطلب بنجاح',
            201
        );
    }
}
