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
            'requestedDeliveryAt' => $this->requested_delivery_at?->format('Y-m-d H:i'),

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
                    return [
                        'store' => $store ? [
                            'id' => $store->id,
                            'storeName' => $store->store_name,
                            'storeLogo' => $store->store_logo,
                        ] : null,
                        'itemsCount' => $storeItems->count(),
                        'subtotal' => $storeItems->sum('line_total'),
                        'items' => OrderItemResource::collection($storeItems),
                    ];
                })->values();
            }),

            // معلومات السائق
            'driver' => $this->when($this->has_driver && $this->relationLoaded('driver'), [
                'id' => $this->driver?->id,
                'name' => $this->driver?->name,
                'phone' => $this->driver?->phone,
                'vehicleType' => $this->driver?->vehicleType?->name,
            ]),
            'hasDriver' => $this->has_driver,
            'driverAssignedAt' => $this->driver_assigned_at?->format('Y-m-d H:i'),

            // حالة انتظار السائق
            'confirmationExpiresAt' => $this->confirmation_expires_at?->toIso8601String(),
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
                    'userName' => $this->user->user_name,
                    'phone' => $this->user->phone,
                ];
            }),

            'createdAt' => $this->created_at->format('Y-m-d H:i'),
            'updatedAt' => $this->updated_at->format('Y-m-d H:i'),
        ];
    }
}
