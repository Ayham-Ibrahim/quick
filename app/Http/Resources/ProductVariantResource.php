<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // تحديد مصدر الكمية بناءً على إعدادات الفئة الفرعية
        $quantityDependsOnAttributes = $this->product?->subCategory?->quantity_depends_on_attributes ?? false;
        
        // إذا الكمية تعتمد على الـ variant، نتحقق من stock_quantity
        // وإلا نتحقق من كمية المنتج الأساسية
        $isInStock = $quantityDependsOnAttributes 
            ? $this->stock_quantity > 0 
            : ($this->product?->quantity ?? 0) > 0;

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'price' => (float) $this->price,
            'stock_quantity' => (int) $this->stock_quantity,
            'is_active' => (bool) $this->is_active,
            'is_in_stock' => $isInStock,
            'attributes' => $this->whenLoaded('attributes', function () {
                return $this->attributes->map(function ($attr) {
                //     $name = $attr->attribute?->name;
                //     $value = $attr->value?->value;
                    
                //     // تجاهل السمات الفارغة
                //     if (empty($name) || empty($value)) {
                //         return null;
                //     }
                    
                //     return [
                //         'attribute_id' => $attr->attribute_id,
                //         'attribute_name' => $name,
                //         'attribute_value_id' => $attr->attribute_value_id,
                //         'attribute_value' => $value,
                //     ];
                // })->filter()->values();
                    $name = $attr->attribute?->name ?? '';
                    $value = $attr->value?->value ?? '';

                    return [
                        'attribute_id' => $attr->attribute_id,
                        'attribute_name' => $name,
                        'attribute_value_id' => $attr->attribute_value_id,
                        'attribute_value' => $value,
                    ];
                });
            }),
            'created_at' => $this->created_at?->setTimezone('Asia/Damascus')->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->setTimezone('Asia/Damascus')->format('Y-m-d H:i:s'),
        ];
    }
}
