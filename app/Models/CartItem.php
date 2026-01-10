<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'unit_price',
        'notes',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'quantity' => 'integer',
    ];

    /**
     * Item belongs to a cart
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * Item belongs to a product
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Item may belong to a product variant
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Get the line total (quantity * unit_price)
     */
    public function getLineTotalAttribute(): float
    {
        return $this->quantity * $this->unit_price;
    }

    /**
     * Get available stock for this item
     */
    public function getAvailableStockAttribute(): int
    {
        if ($this->variant) {
            return $this->variant->stock_quantity;
        }

        return $this->product->quantity ?? 0;
    }

    /**
     * Check if requested quantity is available
     */
    public function isQuantityAvailable(int $quantity = null): bool
    {
        $qty = $quantity ?? $this->quantity;

        if ($this->variant) {
            return $this->variant->is_active && $this->variant->stock_quantity >= $qty;
        }

        return ($this->product->quantity ?? 0) >= $qty;
    }

    /**
     * Get current price (from variant or product)
     */
    public function getCurrentPriceAttribute(): float
    {
        if ($this->variant) {
            return (float) $this->variant->price;
        }

        return (float) $this->product->current_price;
    }

    /**
     * Check if price has changed since added to cart
     */
    public function hasPriceChanged(): bool
    {
        return abs($this->current_price - $this->unit_price) > 0.01;
    }

    /**
     * Update price to current
     */
    public function syncPrice(): void
    {
        $this->update(['unit_price' => $this->current_price]);
    }

    /**
     * Get formatted attribute string for variant (e.g., "اللون: أحمر، المقاس: XL")
     */
    public function getVariantAttributesStringAttribute(): ?string
    {
        if (!$this->variant) {
            return null;
        }

        $attributes = $this->variant->relationLoaded('attributes')
            ? collect($this->variant->attributes)
            : $this->variant->attributes()->with(['attribute', 'value'])->get();

        if ($attributes->isEmpty()) {
            return null;
        }

        return $attributes->map(function ($attr) {
            $attributeName = $attr->attribute?->name ?? '';
            $valueName = $attr->value?->value ?? '';
            return "{$attributeName}: {$valueName}";
        })->implode('، ');
    }
}
