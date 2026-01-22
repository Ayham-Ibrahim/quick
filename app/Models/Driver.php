<?php

namespace App\Models;

use App\Models\Device;
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
        
        // Current geolocation fields
        'current_lat',
        'current_lng',
        'last_location_update',
        'is_online',
        'last_activity_at',
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
            'current_lat' => 'decimal:7',
            'current_lng' => 'decimal:7',
            'last_location_update' => 'datetime',
            'is_online' => 'boolean',
            'last_activity_at' => 'datetime',
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

    /**
     * Get the device for the driver (single-device only).
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne<Device, Driver>
     */
    public function device()
    {
        return $this->morphOne(Device::class, 'owner');
    }

    /**
     * Register or update FCM token.
     * Drivers only support single device - previous device will be replaced.
     *
     * @param string $fcmToken
     * @return Device
     */
    public function registerDevice(string $fcmToken): Device
    {
        return Device::registerSingleDevice($this, $fcmToken);
    }

    /**
     * Get FCM token for the driver.
     *
     * @return string|null
     */
    public function getFcmToken(): ?string
    {
        return $this->device?->fcm_token;
    }

    /* ═══════════════════════════════════════════════════════════════════
     * Order Relations
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
     * Eligibility Checks
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * Maximum scheduled orders per driver
     */
    const MAX_SCHEDULED_ORDERS = 3;

    /**
     * Count of active immediate regular orders (shipping)
     */
    public function getActiveImmediateOrdersCountAttribute(): int
    {
        return $this->orders()
            ->where('status', Order::STATUS_SHIPPING)
            ->where('is_immediate_delivery', true)
            ->count();
    }

    /**
     * Count of active immediate custom orders (shipping)
     */
    public function getActiveImmediateCustomOrdersCountAttribute(): int
    {
        return $this->customOrders()
            ->where('status', CustomOrder::STATUS_SHIPPING)
            ->where('is_immediate', true)
            ->count();
    }

    /**
     * Count of active scheduled regular orders
     */
    public function getActiveScheduledOrdersCountAttribute(): int
    {
        return $this->orders()
            ->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_SHIPPING])
            ->where('is_immediate_delivery', false)
            ->count();
    }

    /**
     * Count of active scheduled custom orders
     */
    public function getActiveScheduledCustomOrdersCountAttribute(): int
    {
        return $this->customOrders()
            ->whereIn('status', [CustomOrder::STATUS_PENDING, CustomOrder::STATUS_SHIPPING])
            ->where('is_immediate', false)
            ->count();
    }

    /**
     * Total active scheduled orders (regular + custom)
     */
    public function getTotalActiveScheduledOrdersAttribute(): int
    {
        return $this->active_scheduled_orders_count + $this->active_scheduled_custom_orders_count;
    }

    /**
     * Can the driver accept an immediate regular order?
     */
    public function canAcceptImmediateOrder(): bool
    {
        return $this->active_immediate_orders_count === 0;
    }

    /**
     * Can the driver accept an immediate custom order?
     */
    public function canAcceptImmediateCustomOrder(): bool
    {
        return $this->active_immediate_custom_orders_count === 0;
    }

    /**
     * Can the driver accept a scheduled order?
     */
    public function canAcceptScheduledOrder(): bool
    {
        return $this->total_active_scheduled_orders < self::MAX_SCHEDULED_ORDERS;
    }

    /**
     * Get delivery profit ratio by vehicle type
     */
    public function getDeliveryProfitRatioAttribute(): float
    {
        $vehicleType = $this->vehicleType;
        
        if (!$vehicleType) {
            return 0;
        }

        // bike = 1, motorbike = 2 (default)
        $tag = $vehicleType->id === 1 
            ? 'delivery_profit_per_ride_bike' 
            : 'delivery_profit_per_ride_motorbike';

        return ProfitRatios::getValueByTag($tag) ?? 0;
    }

    /**
     * Does wallet balance suffice for delivery?
     */
    public function hasEnoughBalanceForDelivery(): bool
    {
        $walletBalance = $this->wallet?->balance ?? 0;
        return $walletBalance >= $this->delivery_profit_ratio;
    }

    /**
     * Is the driver eligible to accept immediate regular orders?
     */
    public function isEligibleForImmediateOrder(): bool
    {
        return $this->is_active 
            && $this->hasEnoughBalanceForDelivery() 
            && $this->canAcceptImmediateOrder();
    }

    /**
     * Is the driver eligible to accept immediate custom orders?
     */
    public function isEligibleForImmediateCustomOrder(): bool
    {
        return $this->is_active 
            && $this->hasEnoughBalanceForDelivery() 
            && $this->canAcceptImmediateCustomOrder();
    }

    /**
     * Is the driver eligible to accept scheduled orders?
     */
    public function isEligibleForScheduledOrder(): bool
    {
        return $this->is_active 
            && $this->hasEnoughBalanceForDelivery() 
            && $this->canAcceptScheduledOrder();
    }
}
