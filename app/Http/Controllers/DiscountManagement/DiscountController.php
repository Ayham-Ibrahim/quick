<?php

namespace App\Http\Controllers\DiscountManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\DiscountRequests\StoreDiscountRequest;
use App\Http\Requests\DiscountRequests\updateDiscountRequest;
use App\Models\DiscountManagement\Discount;
use App\Services\DiscountManagement\DiscountService;

class DiscountController extends Controller
{
    public function __construct(
        protected DiscountService $discountService
    ) {}

    public function index()
    {
        $discounts = Discount::paginate(10);
        return $this->paginate($discounts);
    }

    public function store(StoreDiscountRequest $request)
    {
        $discount = $this->discountService->create($request->validated());

        return $this->success(
            $discount,
            'تم إنشاء الخصم بنجاح',
            201
        );
    }

    public function show(Discount $discount)
    {
        return $this->success($discount);
    }

    public function update(updateDiscountRequest $request, Discount $discount)
    {
        $discount = $this->discountService->update(
            $discount,
            $request->validated()
        );

        return $this->success(
            $discount,
            'تم تحديث الخصم بنجاح'
        );
    }

    public function destroy(Discount $discount)
    {
        $this->discountService->delete($discount);

        return $this->success(
            null,
            'تم حذف الخصم بنجاح'
        );
    }
}
