<?php

namespace App\Models;

use App\Models\ProductImage;
use App\Models\Categories\SubCategory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
     protected $fillable = [
        'store_id', 'name', 'description', 'quantity',
        'current_price', 'previous_price', 'sub_category_id'
    ];

    // public function store() {
    //     return $this->belongsTo(Store::class);
    // }

    public function subCategory() {
        return $this->belongsTo(SubCategory::class);
    }

    public function images() {
        return $this->hasMany(ProductImage::class);
    }
}
