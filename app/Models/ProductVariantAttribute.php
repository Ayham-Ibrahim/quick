<?php

namespace App\Models;

use App\Models\ProductVariant;
use App\Models\Attribute\Attribute;
use Illuminate\Database\Eloquent\Model;
use App\Models\Attribute\AttributeValue;

class ProductVariantAttribute extends Model
{
    protected $fillable = [
        'product_variant_id',
        'attribute_id',
        'attribute_value_id',
    ];

    /**
     * Belongs to variant
     */
    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Attribute (Color, Size)
     * Include soft-deleted attributes to prevent null values
     */
    public function attribute()
    {
        return $this->belongsTo(Attribute::class)->withTrashed();
    }

    /**
     * Attribute value (Red, XL)
     * Include soft-deleted values to prevent null values
     */
    public function value()
    {
        return $this->belongsTo(AttributeValue::class, 'attribute_value_id')->withTrashed();
    }
}
