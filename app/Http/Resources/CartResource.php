<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
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
            'notes' => $this->notes,

            // Calculated totals
            'items_count' => $this->items->count(),
            'total_quantity' => $this->total_items,
            'subtotal' => (float) $this->subtotal,
            'total' => (float) $this->total,

            // Items
            'items' => CartItemResource::collection($this->whenLoaded('items')),

            // Group items by store (useful for multi-store carts)
            'items_by_store' => $this->when($this->relationLoaded('items'), function () {
                return $this->items->groupBy(function ($item) {
                    return $item->product->store_id ?? 0;
                })->map(function ($storeItems, $storeId) {
                    $store = $storeItems->first()->product->store;
                    return [
                        'store' => $store ? [
                            'id' => $store->id,
                            'store_name' => $store->store_name,
                            'store_logo' => $store->store_logo,
                        ] : null,
                        'items_count' => $storeItems->count(),
                        'subtotal' => $storeItems->sum(fn($item) => $item->line_total),
                        'items' => CartItemResource::collection($storeItems),
                    ];
                })->values();
            }),

            'is_empty' => $this->isEmpty(),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
