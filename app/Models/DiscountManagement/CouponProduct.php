<?php

namespace App\Models\DiscountManagement;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

class CouponProduct extends Model
{
    protected $fillable = [
        'coupon_id',
        'product_id'
    ];

    public function product(){
        return $this->belongsTo(Product::class);
    }

    public function coupon(){
        return $this->belongsTo(Coupon::class);
    }

}
