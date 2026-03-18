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
        $variantData = $this->getVariantData();
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
            'variantDetails' => $this->variant_details ?? $variantData['attributes_string'],

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


    /**
     * حساب بيانات المتغير مرة واحدة
     */
    private function getVariantData(): array
    {
        if (!$this->product_variant_id) {
            return ['variant' => null, 'attributes_string' => null];
        }

        // تحميل العلاقة إذا لم تكن محملة
        if (!$this->resource->relationLoaded('variant')) {
            $this->resource->load([
                'variant.attributes.attribute' => fn($q) => $q->withTrashed(),
                'variant.attributes.value' => fn($q) => $q->withTrashed(),
            ]);
        }

        if (!$this->variant) {
            return ['variant' => null, 'attributes_string' => null];
        }

        $attributes = $this->variant->relationLoaded('attributes')
            ? $this->variant->attributes
            : collect([]);

        // حساب السمات المفلترة
        $filteredAttributes = $attributes->map(function ($attr) {
            $name = trim($attr->attribute?->name ?? '');
            $value = trim($attr->value?->value ?? '');
            
            if (empty($name) || empty($value)) {
                return null;
            }
            
            return [
                'attribute_name' => $name,
                'attribute_value' => $value,
            ];
        })->filter()->values();

        // بناء attributes_string
        $attributesString = $filteredAttributes->isEmpty() 
            ? null 
            : $filteredAttributes->map(fn($a) => "{$a['attribute_name']}: {$a['attribute_value']}")->implode('، ');

        return [
            'attributes_string' => $attributesString,
            'variant' => [
                'id' => $this->variant->id,
                'sku' => $this->variant->sku,
                'price' => (float) $this->variant->price,
                'stock_quantity' => $this->variant->stock_quantity,
                'is_active' => (bool) $this->variant->is_active,
                'attributes_string' => $attributesString,
                'attributes' => $filteredAttributes,
            ],
        ];
    }
}
