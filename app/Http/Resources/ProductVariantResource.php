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
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'price' => (float) $this->price,
            'stock_quantity' => (int) $this->stock_quantity,
            'is_active' => (bool) $this->is_active,
            'is_in_stock' => $this->stock_quantity > 0,
            'attributes' => $this->whenLoaded('attributes', function () {
                return $this->attributes->map(function ($attr) {
                    return [
                        'attribute_id' => $attr->attribute_id,
                        'attribute_name' => $attr->attribute?->name,
                        'attribute_value_id' => $attr->attribute_value_id,
                        'attribute_value' => $attr->value?->value,
                    ];
                });
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
