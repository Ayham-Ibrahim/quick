<?php

namespace App\Services\DiscountManagement;

use App\Models\DiscountManagement\Coupon;
use App\Models\DiscountManagement\CouponUsage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CouponService extends \App\Services\Service
{
    /**
     * Apply a coupon to an order, validate its usage rules,
     * and calculate the discount amount.
     *
     * @param  Coupon     $coupon
     * @param  int        $userId
     * @param  int        $orderId
     * @param  float      $orderTotal
     * @param  int|null   $productId
     * @return array{
     *     discount: float,
     *     final_total: float
     * }
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function apply(
        Coupon $coupon,
        int $userId,
        int $orderId,
        float $orderTotal,
        ?int $productId
    ): array {
        return DB::transaction(function () use (
            $coupon,
            $userId,
            $orderId,
            $orderTotal,
            $productId
        ) {
            // 🔒 Lock لمنع race condition
            $coupon = Coupon::where('id', $coupon->id)
                ->lockForUpdate()
                ->withCount('usages')
                ->with('products')
                ->first();

            // 1️⃣ تحقق عام
            if (! $coupon || ! $coupon->is_active) {
                $this->throwExceptionJson(
                    'الكوبون غير صالح حالياً',
                    422
                );
            }

            // 2️⃣ تحقق المنتج
            if ($productId && ! $coupon->products->contains('id', $productId)) {
                $this->throwExceptionJson(
                    'الكوبون غير صالح لهذا المنتج',
                    422
                );
            }

            // 3️⃣ تحقق المستخدم
            $usedByUser = CouponUsage::where('coupon_id', $coupon->id)
                ->where('user_id', $userId)
                ->count();

            if ($usedByUser >= $coupon->usage_limit_per_user) {
                $this->throwExceptionJson(
                    'لقد تجاوزت الحد المسموح لاستخدام الكوبون',
                    422
                );
            }
            $alreadyUsedForOrder = CouponUsage::where('coupon_id', $coupon->id)
                ->where('order_id', $orderId)
                ->exists();

            if ($alreadyUsedForOrder) {
                $this->throwExceptionJson(
                    'تم استخدام هذا الكوبون مسبقاً لهذا الطلب',
                    422
                );
            }
            // 4️⃣ حساب الخصم
            $discount = $coupon->type === 'percentage'
                ? ($orderTotal * $coupon->amount / 100)
                : min($coupon->amount, $orderTotal);

            // // 5️⃣ تسجيل الاستخدام
            // CouponUsage::create([
            //     'coupon_id' => $coupon->id,
            //     'user_id' => $userId,
            //     'order_id' => $orderId,
            //     'status' => 'reserved',
            //     'expires_at' => now()->addMinutes(15),
            // ]);

            // // عند تأكيد الطلب لاحقاً، قم بتحديث الحالة إلى 'used'
            // // CouponUsage::where('order_id', $orderId)
            // //     ->where('coupon_id', $coupon->id)
            // //     ->update([
            // //         'status' => 'used',
            // //         'expires_at' => null
            // //     ]);

            return [
                'discount' => round($discount, 2),
                'final_total' => round($orderTotal - $discount, 2),
                // 'status' => 'reserved',
                // 'expires_at' => now()->addMinutes(15),
            ];
        });
    }

    /**
     * Create a new coupon and attach it to selected products.
     *
     * @param  array  $data
     * @return Coupon
     */
    public function storeCoupon(array $data): Coupon
    {
        return DB::transaction(function () use ($data) {
            $data['code'] = $this->generate(7);
            $coupon = Coupon::create($data);

            if (! empty($data['product_ids'])) {
                $coupon->products()->attach($data['product_ids']);
            }

            return $coupon->load(['store', 'products.store']);
        });
    }
    /**
     * Update an existing coupon and sync its related products.
     *
     * @param  Coupon  $coupon
     * @param  array   $data
     * @return Coupon
     */

    public function updateCoupon(Coupon $coupon, array $data): Coupon
    {
        return DB::transaction(function () use ($coupon, $data) {
            $coupon->update($data);

            if (array_key_exists('product_ids', $data)) {
                $coupon->products()->sync($data['product_ids'] ?? []);
            }

            return $coupon->load(['store', 'products.store']);
        });
    }
    /**
     * Delete a coupon and remove all related product associations.
     *
     * @param  Coupon  $coupon
     * @return void
     */
    public function deleteCoupon(Coupon $coupon): void
    {
        DB::transaction(function () use ($coupon) {
            $coupon->products()->detach();
            $coupon->delete();
        });
    }
    /**
     * Generate a unique coupon code.
     *
     * @param  int     $length  Length of the generated code
     * @param  string  $prefix  Optional prefix for the code
     * @return string
     */
    public function generate(int $length = 10, string $prefix = ''): string
    {
        do {
            $code = strtoupper(
                $prefix . Str::random($length)
            );
        } while (Coupon::where('code', $code)->exists());

        return $code;
    }
}
