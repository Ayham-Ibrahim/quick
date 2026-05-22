<?php

namespace App\Models;

use App\Models\Product;
use App\Models\ProductVariantAttribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'sku',
        'price',
        'base_price_usd',
        'stock_quantity',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'base_price_usd' => 'decimal:6',
        'is_active' => 'boolean',
    ];

    /**
     * Variant belongs to product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Variant has many attributes (Color, Size...)
     */
    public function attributes()
    {
        return $this->hasMany(ProductVariantAttribute::class);
    }
}
