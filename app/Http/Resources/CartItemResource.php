<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
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
            'quantity' => $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'line_total' => (float) $this->line_total,
            'notes' => $this->notes,

            // Stock info
            'available_stock' => $this->available_stock,
            'is_available' => $this->isQuantityAvailable(),

            // Price change detection
            'current_price' => (float) $this->current_price,
            'has_price_changed' => $this->hasPriceChanged(),

            // Product details
            'product' => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'description' => $this->product->description,
                'current_price' => (float) $this->product->current_price,
                'previous_price' => $this->product->previous_price ? (float) $this->product->previous_price : null,
                'image' => $this->product->images->first()?->image,
                'store' => $this->whenLoaded('product', function () {
                    return $this->product->store ? [
                        'id' => $this->product->store->id,
                        'store_name' => $this->product->store->store_name,
                        'store_logo' => $this->product->store->store_logo,
                    ] : null;
                }),
            ],

            // Variant details (if applicable)
            'variant' => $this->when($this->variant, function () {
                $attributes = $this->variant->relationLoaded('attributes')
                    ? $this->variant->attributes
                    : collect($this->variant->attributes ?? []);

                // حساب السمات المفلترة مرة واحدة
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

                // بناء attributes_string من نفس البيانات
                $attributesString = $filteredAttributes->isEmpty() 
                    ? null 
                    : $filteredAttributes->map(fn($a) => "{$a['attribute_name']}: {$a['attribute_value']}")->implode('، ');

                return [
                    'id' => $this->variant->id,
                    'sku' => $this->variant->sku,
                    'price' => (float) $this->variant->price,
                    'stock_quantity' => $this->variant->stock_quantity,
                    'is_active' => $this->variant->is_active,
                    'attributes_string' => $attributesString,
                    'attributes' => $filteredAttributes,
                ];
            }),

            'created_at' => $this->created_at?->setTimezone('Asia/Damascus')->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->setTimezone('Asia/Damascus')->format('Y-m-d H:i:s'),
        ];
    }
}
