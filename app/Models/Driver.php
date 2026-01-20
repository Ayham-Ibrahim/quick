<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Driver extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens,SoftDeletes;
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'driver_name',
        'phone',
        'password',

        'driver_image',
        'front_id_image',
        'back_id_image',

        'city',
        'v_location',
        'h_location',
        'is_active',
        'vehicle_type_id',
    ];
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Define the relationship to the VehicleType model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<VehicleType, Driver>
     */
    public function vehicleType()
    {
        return $this->belongsTo(VehicleType::class, 'vehicle_type_id');
    }
    // Define the relationship to the Wallet model.
    public function wallet()
    {
        return $this->morphOne(Wallet::class, 'owner');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'driver_id');
    }

    public function ratings()
    {
        return $this->morphMany(Rating::class, 'rateable');
    }

    public function averageRating()
    {
        return $this->ratings()->avg('rating') ?? 0;
    }

    /* ═══════════════════════════════════════════════════════════════════
     * علاقات الطلبات - Order Relations
     * ═══════════════════════════════════════════════════════════════════ */

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function customOrders()
    {
        return $this->hasMany(CustomOrder::class);
    }

    /* ═══════════════════════════════════════════════════════════════════
     * التحقق من الأهلية - Eligibility Checks
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * الحد الأقصى للطلبات المجدولة للسائق
     */
    const MAX_SCHEDULED_ORDERS = 3;

    /**
     * عدد الطلبات العادية الفورية النشطة (shipping)
     */
    public function getActiveImmediateOrdersCountAttribute(): int
    {
        return $this->orders()
            ->where('status', Order::STATUS_SHIPPING)
            ->where('is_immediate_delivery', true)
            ->count();
    }

    /**
     * عدد الطلبات الخاصة الفورية النشطة (shipping)
     */
    public function getActiveImmediateCustomOrdersCountAttribute(): int
    {
        return $this->customOrders()
            ->where('status', CustomOrder::STATUS_SHIPPING)
            ->where('is_immediate', true)
            ->count();
    }

    /**
     * عدد الطلبات العادية المجدولة النشطة
     */
    public function getActiveScheduledOrdersCountAttribute(): int
    {
        return $this->orders()
            ->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_SHIPPING])
            ->where('is_immediate_delivery', false)
            ->count();
    }

    /**
     * عدد الطلبات الخاصة المجدولة النشطة
     */
    public function getActiveScheduledCustomOrdersCountAttribute(): int
    {
        return $this->customOrders()
            ->whereIn('status', [CustomOrder::STATUS_PENDING, CustomOrder::STATUS_SHIPPING])
            ->where('is_immediate', false)
            ->count();
    }

    /**
     * إجمالي الطلبات المجدولة النشطة (عادية + خاصة)
     */
    public function getTotalActiveScheduledOrdersAttribute(): int
    {
        return $this->active_scheduled_orders_count + $this->active_scheduled_custom_orders_count;
    }

    /**
     * هل يمكن للسائق قبول طلب فوري عادي؟
     */
    public function canAcceptImmediateOrder(): bool
    {
        return $this->active_immediate_orders_count === 0;
    }

    /**
     * هل يمكن للسائق قبول طلب فوري خاص؟
     */
    public function canAcceptImmediateCustomOrder(): bool
    {
        return $this->active_immediate_custom_orders_count === 0;
    }

    /**
     * هل يمكن للسائق قبول طلب مجدول؟
     */
    public function canAcceptScheduledOrder(): bool
    {
        return $this->total_active_scheduled_orders < self::MAX_SCHEDULED_ORDERS;
    }

    /**
     * الحصول على نسبة الربح من التوصيل حسب نوع المركبة
     */
    public function getDeliveryProfitRatioAttribute(): float
    {
        $vehicleType = $this->vehicleType;
        
        if (!$vehicleType) {
            return 0;
        }

        // bike = 1, motorbike = 2 (افتراضياً)
        $tag = $vehicleType->id === 1 
            ? 'delivery_profit_per_ride_bike' 
            : 'delivery_profit_per_ride_motorbike';

        return ProfitRatios::getValueByTag($tag) ?? 0;
    }

    /**
     * هل رصيد المحفظة كافي للتوصيل؟
     */
    public function hasEnoughBalanceForDelivery(): bool
    {
        $walletBalance = $this->wallet?->balance ?? 0;
        return $walletBalance >= $this->delivery_profit_ratio;
    }

    /**
     * هل السائق مؤهل لقبول طلب فوري عادي؟
     */
    public function isEligibleForImmediateOrder(): bool
    {
        return $this->is_active 
            && $this->hasEnoughBalanceForDelivery() 
            && $this->canAcceptImmediateOrder();
    }

    /**
     * هل السائق مؤهل لقبول طلب فوري خاص؟
     */
    public function isEligibleForImmediateCustomOrder(): bool
    {
        return $this->is_active 
            && $this->hasEnoughBalanceForDelivery() 
            && $this->canAcceptImmediateCustomOrder();
    }

    /**
     * هل السائق مؤهل لقبول طلب مجدول؟
     */
    public function isEligibleForScheduledOrder(): bool
    {
        return $this->is_active 
            && $this->hasEnoughBalanceForDelivery() 
            && $this->canAcceptScheduledOrder();
    }
}
