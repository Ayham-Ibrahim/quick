<?php

namespace App\Services;

use App\Models\Product;
use App\Models\DiscountManagement\Coupon;
use Illuminate\Support\Facades\DB;

class HomeService extends Service
{
    /**
     * عروض الإدارة - المنتجات التي عليها كوبونات نشطة
     * Admin Offers - Products with active coupons
     */
    public function getAdminOffers(int $limit = 20)
    {
        $now = now();

        // Get products that have active coupons
        return Product::query()
            ->select([
                'products.id',
                'products.name',
                'products.description',
                'products.current_price',
                'products.previous_price',
                'products.quantity',
            ])
            ->whereHas('coupons', function ($query) use ($now) {
                $query
                    // Coupon has started or no start date
                    ->where(function ($q) use ($now) {
                        $q->whereNull('start_at')
                            ->orWhere('start_at', '<=', $now);
                    })
                    // Coupon hasn't ended or no end date
                    ->where(function ($q) use ($now) {
                        $q->whereNull('end_at')
                            ->orWhere('end_at', '>=', $now);
                    })
                    // Usage limit not exceeded
                    ->whereRaw('(SELECT COUNT(*) FROM coupon_usages WHERE coupon_usages.coupon_id = coupons.id) < coupons.usage_limit_total');
            })
            ->with([
                'mainProductImage',
                'coupons' => function ($query) use ($now) {
                    $query
                        ->where(function ($q) use ($now) {
                            $q->whereNull('start_at')
                                ->orWhere('start_at', '<=', $now);
                        })
                        ->where(function ($q) use ($now) {
                            $q->whereNull('end_at')
                                ->orWhere('end_at', '>=', $now);
                        })
                        ->select('coupons.id', 'coupons.code', 'coupons.type', 'coupons.amount', 'coupons.end_at')
                        ->limit(1);
                }
            ])
            ->withAvg('ratings', 'rating')
            ->withCount('ratings')
            ->accepted()
            ->inRandomOrder()
            ->take($limit)
            ->get()
            ->map(function ($product) {
                $coupon = $product->coupons->first();
                $discountedPrice = $this->calculateDiscountedPrice(
                    $product->current_price,
                    $coupon?->type,
                    $coupon?->amount
                );

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'current_price' => (float) $product->current_price,
                    'discounted_price' => $discountedPrice,
                    'image' => $product->mainProductImage?->image ?? null,
                    'quantity' => (int) $product->quantity,
                    'average_rating' => round($product->ratings_avg_rating ?? 0, 1),
                    'ratings_count' => (int) $product->ratings_count,
                    'coupon' => $coupon ? [
                        'code' => $coupon->code,
                        'type' => $coupon->type,
                        'amount' => (float) $coupon->amount,
                        'ends_at' => $coupon->end_at?->toDateTimeString(),
                    ] : null,
                ];
            });
    }

    /**
     * عروض المتاجر - المنتجات التي لها سعر سابق أعلى من السعر الحالي
     * Store Offers - Products with previous_price > current_price (on sale)
     */
    public function getStoreOffers(int $limit = 20)
    {
        return Product::query()
            ->select([
                'products.id',
                'products.name',
                'products.description',
                'products.current_price',
                'products.previous_price',
                'products.quantity',
            ])
            // Has previous price and it's higher than current (discount)
            ->whereNotNull('previous_price')
            ->whereColumn('previous_price', '>', 'current_price')
            ->with('mainProductImage')
            ->withAvg('ratings', 'rating')
            ->withCount('ratings')
            ->accepted()
            ->inRandomOrder()
            ->take($limit)
            ->get()
            ->map(function ($product) {
                $discountPercentage = $this->calculateDiscountPercentage(
                    $product->previous_price,
                    $product->current_price
                );

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'current_price' => (float) $product->current_price,
                    'previous_price' => (float) $product->previous_price,
                    'discount_percentage' => $discountPercentage,
                    'image' => $product->mainProductImage?->image ?? null,
                    'quantity' => (int) $product->quantity,
                    'average_rating' => round($product->ratings_avg_rating ?? 0, 1),
                    'ratings_count' => (int) $product->ratings_count,
                ];
            });
    }

    /**
     * Calculate discounted price based on coupon type
     */
    private function calculateDiscountedPrice(?float $price, ?string $type, ?float $amount): ?float
    {
        if (!$price || !$type || !$amount) {
            return null;
        }

        if ($type === 'percentage') {
            return round($price - ($price * $amount / 100), 2);
        }

        // Fixed amount
        return max(0, round($price - $amount, 2));
    }

    /**
     * Calculate discount percentage
     */
    private function calculateDiscountPercentage(float $previousPrice, float $currentPrice): int
    {
        if ($previousPrice <= 0) {
            return 0;
        }

        return (int) round((($previousPrice - $currentPrice) / $previousPrice) * 100);
    }
}
