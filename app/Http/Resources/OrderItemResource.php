<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
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

            // معلومات المنتج
            'productId' => $this->product_id,
            'productName' => $this->product_name,
            'productVariantId' => $this->product_variant_id,
            'variantDetails' => $this->variant_details,

            // الكمية والأسعار
            'quantity' => $this->quantity,
            'unitPrice' => (float) $this->unit_price,
            'subtotal' => (float) $this->subtotal, // unit_price × quantity

            // الخصم
            'discountAmount' => (float) $this->discount_amount,
            'hasDiscount' => $this->has_discount,
            'discountPercentage' => $this->when($this->has_discount, $this->discount_percentage),

            // الإجمالي بعد الخصم
            'lineTotal' => (float) $this->line_total,

            // معلومات المتجر
            'store' => $this->when($this->relationLoaded('store'), function () {
                return [
                    'id' => $this->store->id,
                    'storeName' => $this->store->store_name,
                    'storeLogo' => $this->store->store_logo,
                    'storePhone' => $this->store->phone,
                    'storeCity' => $this->store->city,
                    'v_location' => $this->store->v_location,
                    'h_location' => $this->store->h_location,
                ];
            }, (int) $this->store_id),

            // معلومات المنتج (للعرض)
            'product' => $this->when($this->relationLoaded('product'), function () {
                return [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'currentPrice' => (float) $this->product->current_price,
                    'image' => $this->product->relationLoaded('images') 
                        ? $this->product->images() 
                        : null,
                    // تقييم المنتج
                    'averageRating' => round($this->product->averageRating(), 1),
                    'ratingsCount' => (int) $this->product->ratings()->count(),
                ];
            }),
        ];
    }
}
