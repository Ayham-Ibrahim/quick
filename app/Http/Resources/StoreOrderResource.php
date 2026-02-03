<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource لعرض الطلب من منظور صاحب المتجر
 * 
 * يعرض فقط المنتجات التابعة للمتجر مع حسابات مالية دقيقة:
 * - إجمالي سعر القطع (قبل الخصم)
 * - قيمة الكوبون/الخصم على منتجات المتجر فقط
 * - الإجمالي بعد الخصم
 */
class StoreOrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // جلب معرف المتجر من context
        $storeId = $this->store_context ?? null;

        // فلترة العناصر التابعة للمتجر فقط
        $storeItems = $storeId
            ? $this->items->where('store_id', $storeId)
            : $this->items;

        // حساب الإجماليات للمتجر فقط
        $storeSubtotal = $storeItems->reduce(function ($carry, $item) {
            return $carry + (($item->unit_price ?? 0) * ($item->quantity ?? 0));
        }, 0);

        $storeCouponDiscount = $storeItems->sum('discount_amount');
        $storeTotal = $storeItems->sum('line_total');

        return [
            'id' => $this->id,
            'orderNumber' => 'ORD-' . str_pad($this->id, 6, '0', STR_PAD_LEFT),
            'status' => $this->status,
            'statusText' => $this->status_text,

            // تاريخ ووقت الطلب
            'createdAt' => $this->created_at->format('Y-m-d H:i'),
            'createdAtFormatted' => $this->created_at->format('H:i d/m/Y'),

            // العناصر التابعة للمتجر فقط
            'items' => StoreOrderItemResource::collection($storeItems),
            'itemsCount' => $storeItems->count(),

            // الحسابات المالية للمتجر فقط
            'pricing' => [
                'subtotal' => round($storeSubtotal, 2), // إجمالي القطع (قبل الخصم)
                'couponDiscount' => round($storeCouponDiscount, 2), // قيمة الكوبون
                'total' => round($storeTotal, 2), // الإجمالي (بعد الخصم)
            ],

            // معلومات الكوبون (إن وجد)
            'coupon' => $this->when($this->has_coupon && $storeCouponDiscount > 0, [
                'code' => $this->coupon_code,
                'discountOnStoreItems' => round($storeCouponDiscount, 2),
            ]),

            // ملاحظات العميل
            'notes' => $this->notes,

            // سبب الإلغاء (إن وجد)
            'cancellationReason' => $this->when(
                $this->status === 'cancelled',
                $this->cancellation_reason
            ),

            // عنوان التوصيل
            'delivery' => [
                'address' => $this->delivery_address,
                'lat' => $this->delivery_lat,
                'lng' => $this->delivery_lng,
                'requestedAt' => $this->requested_delivery_at?->format('Y-m-d H:i'),
                'isImmediate' => (bool) $this->is_immediate_delivery,
            ],

            // معلومات العميل
            'customer' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'phone' => $this->user->phone,
                    'image' => $this->user->avatar,
                ];
            }),

            // معلومات السائق
            'driver' => $this->when($this->has_driver && $this->relationLoaded('driver'), function () {
                return [
                    'id' => $this->driver?->id,
                    'name' => $this->driver?->driver_name,
                    'phone' => $this->driver?->phone,
                    'image' => $this->driver?->driver_image,
                    'vehicleType' => $this->driver?->vehicleType?->type,
                    'location' => $this->driver && $this->driver->current_lat && $this->driver->current_lng ? [
                        'lat' => (float) $this->driver->current_lat,
                        'lng' => (float) $this->driver->current_lng,
                    ] : null,
                ];
            }),
            'hasDriver' => $this->has_driver,

            // حالات مفيدة
            'confirmationExpiresAt' => $this->confirmation_expires_at?->toIso8601String(),
            'isConfirmationExpired' => $this->is_confirmation_expired,

            'updatedAt' => $this->updated_at->format('Y-m-d H:i'),
        ];
    }
}
