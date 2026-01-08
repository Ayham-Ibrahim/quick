<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'     => $this->id,
            'code'   => $this->code,
            'type'   => $this->type,
            'amount' => (float) $this->amount,

            'usage_limit_total'    => $this->usage_limit_total,
            'usage_limit_per_user' => $this->usage_limit_per_user,

            'start_at' => $this->start_at,
            'end_at'   => $this->end_at,

            'is_active'    => $this->is_active,
            'total_usage' => $this->total_usage,

            /* ================= Store Info ================= */
            'store' => $this->whenLoaded('store', function () {
                return [
                    'id'   => $this->store->id,
                    'name' => $this->store->store_name,
                ];
            }),

            /* ================= Products ================= */
            'products' => $this->whenLoaded('products', function () {
                return $this->products->map(function ($product) {
                    return [
                        'id'   => $product->id,
                        'name' => $product->name,
                    ];
                })->values();
            }),
        ];
    }
}
