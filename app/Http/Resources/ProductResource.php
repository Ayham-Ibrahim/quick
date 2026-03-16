<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'current_price' => $this->current_price ? (float) $this->current_price : null,
            'previous_price' => $this->previous_price ? (float) $this->previous_price : null,
            'is_accepted' => (bool) $this->is_accepted,

            // Calculated fields
            'has_variants' => $this->variants->count() > 0,
            'total_stock' => $this->calculateTotalStock(),
            'price_range' => $this->getPriceRange(),

            // Relationships
            'store' => $this->whenLoaded('store', function () {
                return [
                    'id' => $this->store->id,
                    'store_name' => $this->store->store_name,
                    'store_logo' => $this->store->store_logo,
                ];
            }),

            'sub_category' => $this->whenLoaded('subCategory', function () {
                return [
                    'id' => $this->subCategory->id,
                    'name' => $this->subCategory->name,
                    'category' => $this->subCategory->category ? [
                        'id' => $this->subCategory->category->id,
                        'name' => $this->subCategory->category->name,
                    ] : null,
                ];
            }),

            'images' => $this->whenLoaded('images', function () {
                return $this->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'image' => $image->image,
                    ];
                });
            }),

            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),

            'ratings' => RatingResource::collection($this->whenLoaded('ratings')),
            'average_rating' => round($this->averageRating(), 1),
            'ratings_count' => $this->ratings()->count(),

            'created_at' => $this->created_at?->setTimezone('Asia/Damascus')->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->setTimezone('Asia/Damascus')->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Calculate total stock from all variants or base quantity
     * 
     * الحالات:
     * 1. منتج بدون variants → الكمية من product.quantity
     * 2. منتج له variants و quantity_depends_on_attributes = true → الكمية من مجموع variant.stock_quantity
     * 3. منتج له variants و quantity_depends_on_attributes = false → الكمية من product.quantity
     */
    protected function calculateTotalStock(): int
    {
        // إذا لا توجد variants، الكمية من المنتج مباشرة
        if ($this->variants->count() === 0) {
            return $this->quantity ?? 0;
        }

        // إذا الكمية تعتمد على الـ variants
        if ($this->subCategory?->quantity_depends_on_attributes) {
            return $this->variants->where('is_active', true)->sum('stock_quantity');
        }

        // الكمية من المنتج (variants للسعر فقط)
        return $this->quantity ?? 0;
    }

    /**
     * Get price range for products with variants
     */
    protected function getPriceRange(): ?array
    {
        if ($this->variants->count() === 0) {
            return null;
        }

        $activeVariants = $this->variants->where('is_active', true);
        
        if ($activeVariants->isEmpty()) {
            return null;
        }

        $minPrice = $activeVariants->min('price');
        $maxPrice = $activeVariants->max('price');

        return [
            'min' => (float) $minPrice,
            'max' => (float) $maxPrice,
            'is_variable' => $minPrice !== $maxPrice,
        ];
    }
}
