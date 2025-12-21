<?php

namespace App\Models\Attribute;

use App\Models\ProductVariantAttribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttributeValue extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'attribute_id',
        'value',
        'slug',
        'is_active',
    ];

    /**
     * Value belongs to an attribute
     */
    public function attribute()
    {
        return $this->belongsTo(Attribute::class);
    }

    /**
     * Value used in variant attributes
     */
    public function variantAttributes()
    {
        return $this->hasMany(ProductVariantAttribute::class);
    }
}
