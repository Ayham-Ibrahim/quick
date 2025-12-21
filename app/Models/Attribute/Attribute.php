<?php

namespace App\Models\Attribute;

use App\Models\ProductVariantAttribute;
use Illuminate\Database\Eloquent\Model;
use App\Models\Attribute\AttributeValue;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attribute extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'is_active',
    ];

    /**
     * Attribute has many values
     */
    public function values()
    {
        return $this->hasMany(AttributeValue::class);
    }

    /**
     * Attribute used in variant attributes
     */
    public function variantAttributes()
    {
        return $this->hasMany(ProductVariantAttribute::class);
    }
}
