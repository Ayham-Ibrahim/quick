<?php

namespace App\Models;

use App\Models\Attribute\Attribute;
use App\Models\Categories\SubCategory;
use Illuminate\Database\Eloquent\Model;

class SubCategoryAttribute extends Model
{
    protected $fillable = [
        'sub_category_id',
        'attribute_id',
    ];

    /**
     * Get the subcategory
     */
    public function subCategory()
    {
        return $this->belongsTo(SubCategory::class);
    }

    /**
     * Get the attribute
     */
    public function attribute()
    {
        return $this->belongsTo(Attribute::class);
    }
}
