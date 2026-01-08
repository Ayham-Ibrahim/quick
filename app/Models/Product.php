<?php

namespace App\Models;

use App\Models\Store;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Categories\SubCategory;

use App\Models\DiscountManagement\Coupon;
use App\Models\DiscountManagement\CouponProduct;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'store_id',
        'name',
        'description',
        'quantity',
        'current_price',
        'previous_price',
        'sub_category_id',
        'is_accepted'
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function subCategory()
    {
        return $this->belongsTo(SubCategory::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function mainProductImage()
    {
        return $this->hasOne(ProductImage::class)->oldest();
    }

    /**
     * Scope للحصول على المنتجات المقبولة (approved).
     */
    public function scopeAccepted($query)
    {
        return $query->where('is_accepted', true);
    }

    /**
     * Scope للحصول على المنتجات غير المقبولة / المعلقة (pending).
     */
    public function scopePending($query)
    {
        return $query->where('is_accepted', false);
    }

    public function ratings()
    {
        return $this->morphMany(Rating::class, 'rateable');
    }
    public function averageRating()
    {
        return $this->ratings()->avg('rating') ?? 0;
    }
    protected $appends = [
        'average_rating',
        'ratings_count',
    ];
    public function getRatingsCountAttribute()
    {
        return $this->ratings()->count();
    }
    public function getAverageRatingAttribute()
    {
        return $this->ratings()->avg('rating') ?? 0;
    }
    public function coupons()
    {
        return $this->belongsToMany(
            Coupon::class,
            'coupon_products'
        )->withTimestamps();
    }

    /**
     * Product has many variants
     */
    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }
    // public function orderItems()
    // {
    //     return $this->hasMany(OrderItem::class);
    // }

}
