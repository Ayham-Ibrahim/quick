<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomOrderResource extends JsonResource
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
            'status' => $this->status,
            'status_text' => $this->status_text,

            // التكاليف
            'delivery_fee' => (float) $this->delivery_fee,
            'distance_km' => (float) $this->distance_km,

            // عنوان التسليم
            'delivery_address' => $this->delivery_address,
            'delivery_lat' => $this->delivery_lat ? (float) $this->delivery_lat : null,
            'delivery_lng' => $this->delivery_lng ? (float) $this->delivery_lng : null,

            // موعد التوصيل
            'is_immediate' => $this->is_immediate,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),

            // حالة التأكيد
            'confirmation_expires_at' => $this->confirmation_expires_at?->toIso8601String(),
            'is_confirmation_expired' => $this->is_confirmation_expired,
            'can_resend_to_drivers' => $this->can_resend_to_drivers,
            'is_available_for_driver' => $this->is_available_for_driver,

            // العناصر
            'items' => CustomOrderItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->items_count,

            // السائق
            'driver' => $this->whenLoaded('driver', function () {
                return [
                    'id' => $this->driver->id,
                    'name' => $this->driver->driver_name,
                    'phone' => $this->driver->phone,
                    'image' => $this->driver->driver_image,
                    'vehicleType' => $this->driver->vehicleType?->type,
                    'lat' => $this->driver->current_lat ? (float) $this->driver->current_lat : null,
                    'lng' => $this->driver->current_lng ? (float) $this->driver->current_lng : null,
                    'lastLocationUpdate' => $this->driver->last_location_update?->toIso8601String(),
                    'isOnline' => (bool) $this->driver->is_online,
                ];
            }),
            'has_driver' => $this->has_driver,
            'driver_assigned_at' => $this->driver_assigned_at?->toIso8601String(),

            // المستخدم (للسائق فقط)
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'user_name' => $this->user->name,
                    'userName' => $this->user->name,
                    'phone' => $this->user->phone,
                    'image' => $this->user->avatar,
                ];
            }),

            // معلومات إضافية
            'notes' => $this->notes,
            'is_cancellable' => $this->is_cancellable,
            'cancellation_reason' => $this->when(
                $this->status === 'cancelled',
                $this->cancellation_reason
            ),
            'can_user_cancel' => $this->can_user_cancel,
            'can_admin_cancel' => $this->can_admin_cancel,

            'created_at' => $this->created_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
