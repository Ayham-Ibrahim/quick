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
        // حساب بيانات المتغير مرة واحدة
        $variantData = $this->getVariantData();

        return [
            'id' => $this->id,

            // معلومات المنتج
            'productId' => $this->product_id,
            'productName' => $this->product_name,
            'productVariantId' => $this->product_variant_id,
            // استخدام القيمة المخزنة أو المحسوبة
            'variantDetails' => $this->variant_details ?? $variantData['attributes_string'],

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
                    'averageRating' => round($this->store->averageRating(), 1),
                    'ratingsCount' => (int) $this->store->ratings()->count(),
                ];
            }, (int) $this->store_id),

            // معلومات المنتج (للعرض)
            'product' => $this->when($this->relationLoaded('product'), function () {
                return [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'currentPrice' => (float) $this->product->current_price,
                    'previousPrice' => (float) $this->product->previous_price,
                    'image' => $this->product->relationLoaded('images') 
                        ? $this->product->images->first()?->image 
                        : null,
                    // تقييم المنتج
                    'averageRating' => round($this->product->averageRating(), 1),
                    'ratingsCount' => (int) $this->product->ratings()->count(),
                ];
            }),

            // تفاصيل المتغير (إن وجد)
            'variant' => $this->when($this->product_variant_id && $variantData['variant'], $variantData['variant']),
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
