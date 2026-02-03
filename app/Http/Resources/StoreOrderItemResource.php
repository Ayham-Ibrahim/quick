<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource لعرض عنصر طلب من منظور صاحب المتجر
 */
class StoreOrderItemResource extends JsonResource
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
            'productImage' => $this->when($this->relationLoaded('product'), function () {
                return $this->product?->relationLoaded('images')
                    ? $this->product->images->first()?->image
                    : null;
            }),

            // معلومات المتغير (إن وجد)
            'productVariantId' => $this->product_variant_id,
            'variantDetails' => $this->variant_details,

            // الكمية والأسعار
            'quantity' => $this->quantity,
            'unitPrice' => (float) $this->unit_price,
            'subtotal' => round((float) $this->unit_price * $this->quantity, 2), // السعر × الكمية

            // الخصم
            'discountAmount' => (float) $this->discount_amount,
            'hasDiscount' => $this->has_discount,

            // الإجمالي بعد الخصم
            'lineTotal' => (float) $this->line_total,
        ];
    }
}
