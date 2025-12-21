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
        'stock_quantity',
        'is_active',
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
