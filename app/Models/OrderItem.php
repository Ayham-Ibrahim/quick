<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'store_id',
        'quantity',
        'unit_price',
        'discount_amount',
        'line_total',
        'product_name',
        'variant_details',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    /* ================= Relations ================= */

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /* ================= Accessors ================= */

    /**
     * السعر الإجمالي قبل الخصم
     */
    public function getSubtotalAttribute(): float
    {
        return $this->unit_price * $this->quantity;
    }

    /**
     * هل يوجد خصم على هذا العنصر؟
     */
    public function getHasDiscountAttribute(): bool
    {
        return $this->discount_amount > 0;
    }

    /**
     * نسبة الخصم (للعرض فقط)
     */
    public function getDiscountPercentageAttribute(): float
    {
        if ($this->subtotal <= 0) {
            return 0;
        }

        return round(($this->discount_amount / $this->subtotal) * 100, 2);
    }

    /* ================= Methods ================= */

    /**
     * حساب line_total بناءً على السعر والكمية والخصم
     */
    public static function calculateLineTotal(float $unitPrice, int $quantity, float $discount = 0): float
    {
        return round(($unitPrice * $quantity) - $discount, 2);
    }

    /**
     * بناء تفاصيل المتغير كنص
     * مثال: "اللون: أحمر، المقاس: XL"
     */
    public static function buildVariantDetails(?ProductVariant $variant): ?string
    {
        if (!$variant) {
            return null;
        }

        $variant->load('attributes.attribute', 'attributes.value');

        $details = $variant->attributes->map(function ($attr) {
            $attrName = $attr->attribute->name ?? '';
            $valueName = $attr->value->value ?? '';
            return "{$attrName}: {$valueName}";
        })->filter()->implode('، ');

        return $details ?: null;
    }
}
