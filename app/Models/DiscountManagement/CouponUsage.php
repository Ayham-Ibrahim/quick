<?php

namespace App\Models\DiscountManagement;

use App\Models\UserManagement\User;
use Illuminate\Database\Eloquent\Model;

class CouponUsage extends Model
{
    protected $fillable = [
        'coupon_id',
        'user_id',
        'order_id',
        // 'status',
        // 'expires_at'
    ];
     public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }


    public function user()
    {
        return $this->belongsTo(User::class);
    }


    // public function order()
    // {
    //     return $this->belongsTo(Order::class);
    // }
}
