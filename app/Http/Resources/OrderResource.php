<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'orderNumber' => 'ORD-' . str_pad($this->id, 6, '0', STR_PAD_LEFT),
            'status' => $this->status,
            'statusText' => $this->status_text,

            // المبالغ
            'subtotal' => (float) $this->subtotal,
            'discountAmount' => (float) $this->discount_amount,
            'deliveryFee' => (float) $this->delivery_fee,
            'total' => (float) $this->total,

            // معلومات الكوبون
            'coupon' => $this->when($this->has_coupon, [
                'code' => $this->coupon_code,
                'discountAmount' => (float) $this->discount_amount,
            ]),

            // عنوان التوصيل
            'deliveryAddress' => $this->delivery_address,
            'deliveryLat' => $this->delivery_lat,
            'deliveryLng' => $this->delivery_lng,
            'requestedDeliveryAt' => $this->requested_delivery_at?->setTimezone('Asia/Damascus')->format('Y-m-d H:i'),

            // معلومات خاصة بطلب متجر واحد (لصفحة طلبات المتجر في ال Admin)
            $this->mergeWhen(isset($this->store_context), function () {
                $storeId = $this->store_context;
                $storeItems = $this->items->where('store_id', $storeId);
                $store = $storeItems->first()?->store;

                $storeSubtotal = $storeItems->reduce(function ($carry, $item) {
                    return $carry + (($item->unit_price ?? 0) * ($item->quantity ?? 0));
                }, 0);

                $storeDiscount = $storeItems->sum('discount_amount');
                $storeTotal = $storeItems->sum('line_total');

                return [
                    'store' => $store ? [
                        'id' => $store->id,
                        'storeName' => $store->store_name,
                        'phone' => $store->phone,
                        'storeLogo' => $store->store_logo,
                        'averageRating' => round($store->averageRating(), 1),
                        'ratingsCount' => (int) $store->ratings()->count(),
                    ] : [ 'id' => $storeId ],

                    // Per-store financials (explicit Price names)
                    'storeSubtotalPriceBeforeDiscount' => (float) round($storeSubtotal, 2), // sum(unit_price * qty) before discounts (price)
                    'storeCouponDiscountPrice' => (float) round($storeDiscount, 2), // total coupon/discount applied to this store's items (price)
                    'storeTotalPriceAfterDiscount' => (float) round($storeTotal, 2), // final total for store items after discounts (price)

                    // Order-level financials (useful for admin store view)
                    'orderTotalPriceFinal' => (float) round($this->total, 2), // final amount (after coupon + delivery) (price)
                    'orderCouponDiscountPrice' => (float) round($this->discount_amount, 2), // coupon amount applied on order (price)
                    'orderPriceBeforeCouponAndDeliveryPrice' => (float) round(($this->subtotal + $this->delivery_fee), 2), // subtotal + delivery before coupon (price)
                ];
            }),

            // ملاحظات
            'notes' => $this->notes,
            'cancellationReason' => $this->when(
                $this->status === 'cancelled',
                $this->cancellation_reason
            ),

            // إحصائيات
            'itemsCount' => $this->items_count,
            'isCancellable' => $this->is_cancellable,
            'canUserCancel' => $this->can_user_cancel,
            'canAdminCancel' => $this->can_admin_cancel,

            // العناصر
            'items' => OrderItemResource::collection($this->whenLoaded('items')),

            // العناصر مجمعة حسب المتجر
            'itemsByStore' => $this->when($this->relationLoaded('items'), function () {
                return $this->items->groupBy('store_id')->map(function ($storeItems, $storeId) {
                    $store = $storeItems->first()->store;
                    $storeSubtotal = $storeItems->reduce(function ($carry, $item) {
                    return $carry + (($item->unit_price ?? 0) * ($item->quantity ?? 0));
                }, 0);

                $storeDiscount = $storeItems->sum('discount_amount');
                $storeTotal = $storeItems->sum('line_total');

                return [
                        'store' => $store ? [
                            'id' => $store->id,
                            'storeName' => $store->store_name,
                            'storeLogo' => $store->store_logo,
                            'phone' => $store->phone,
                            'storeCity' => $store->city,
                            'v_location' => $store->v_location,
                            'h_location' => $store->h_location,
                            'averageRating' => round($store->averageRating(), 1),
                            'ratingsCount' => (int) $store->ratings()->count(),
                        ] : null,
                        'itemsCount' => $storeItems->count(),
                        // Backward-compatible subtotal (equals final after discounts)
                        'subtotal' => $storeTotal,
                        // Explicit financial breakdown per store (clear Price names for front-end)
                        'storeSubtotalPriceBeforeDiscount' => (float) round($storeSubtotal, 2), // before discounts (price)
                        'storeCouponDiscountPrice' => (float) round($storeDiscount, 2),
                        'storeSubtotalPriceAfterDiscount' => (float) round($storeTotal, 2), // after discounts (price)
                        'storeTotalPriceAfterDiscount' => (float) round($storeTotal, 2), // alias for clarity (price)
                        'items' => OrderItemResource::collection($storeItems),
                    ];
                })->values();
            }),

            // معلومات السائق
            'driver' => $this->when($this->has_driver && $this->relationLoaded('driver'), [
                'id' => $this->driver?->id,
                'name' => $this->driver?->driver_name,
                'phone' => $this->driver?->phone,
                'image' => $this->driver?->driver_image,
                'vehicleType' => $this->driver?->vehicleType?->type,
                'location' => $this->driver && $this->driver->current_lat && $this->driver->current_lng ? [
                    'lat' => (float) $this->driver->current_lat,
                    'lng' => (float) $this->driver->current_lng,
                    'updatedAt' => $this->driver->last_location_update?->setTimezone('Asia/Damascus')->toIso8601String(),
                    'isOnline' => (bool) $this->driver->is_online,
                ] : null,
            ]),
            'hasDriver' => $this->has_driver,
            'driverAssignedAt' => $this->driver_assigned_at?->setTimezone('Asia/Damascus')->format('Y-m-d H:i'),

            // حالة انتظار السائق
            'confirmationExpiresAt' => $this->confirmation_expires_at?->setTimezone('Asia/Damascus')->toIso8601String(),
            'isConfirmationExpired' => $this->is_confirmation_expired,
            'canResendToDrivers' => $this->can_resend_to_drivers,
            'isAvailableForDriver' => $this->is_available_for_driver,
            'isImmediateDelivery' => $this->is_immediate_delivery,
            'canReorder' => $this->can_reorder,
            'canDriverCancelDelivery' => $this->can_driver_cancel_delivery,

            // معلومات المستخدم (للسائق)
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'userName' => $this->user->name,
                    'phone' => $this->user->phone,
                    'image' => $this->user->avatar,
                ];
            }),

            'createdAt' => $this->created_at->setTimezone('Asia/Damascus')->format('Y-m-d H:i'),
            'updatedAt' => $this->updated_at->setTimezone('Asia/Damascus')->format('Y-m-d H:i'),
        ];
    }
}
