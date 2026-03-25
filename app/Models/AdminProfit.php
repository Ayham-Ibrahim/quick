<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminProfit extends Model
{
    protected $fillable = [
        'source_type',
        'source_id',
        'order_type',
        'order_id',
        'amount',
        'description',
        'is_settled',
        'settled_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_settled' => 'boolean',
        'settled_at' => 'datetime',
    ];

    /* ================= Constants ================= */
    
    const SOURCE_DRIVER = 'driver';
    const SOURCE_STORE = 'store';
    
    const ORDER_TYPE_REGULAR = 'order';
    const ORDER_TYPE_CUSTOM = 'custom_order';

    /* ================= Relations ================= */

    /**
     * Get the source (Driver or Store)
     */
    public function source()
    {
        if ($this->source_type === self::SOURCE_DRIVER) {
            return $this->belongsTo(Driver::class, 'source_id');
        }
        return $this->belongsTo(Store::class, 'source_id');
    }

    /**
     * Get the related order
     */
    public function order()
    {
        if ($this->order_type === self::ORDER_TYPE_REGULAR) {
            return $this->belongsTo(Order::class, 'order_id');
        }
        return $this->belongsTo(CustomOrder::class, 'order_id');
    }

    /* ================= Scopes ================= */

    public function scopeFromDrivers($query)
    {
        return $query->where('source_type', self::SOURCE_DRIVER);
    }

    public function scopeFromStores($query)
    {
        return $query->where('source_type', self::SOURCE_STORE);
    }

    public function scopeUnsettled($query)
    {
        return $query->where('is_settled', false);
    }

    public function scopeSettled($query)
    {
        return $query->where('is_settled', true);
    }

    public function scopeForStore($query, int $storeId)
    {
        return $query->fromStores()->where('source_id', $storeId);
    }

    /* ================= Static Methods ================= */

    /**
     * Record profit from driver delivery
     */
    public static function recordDriverProfit(
        int $driverId,
        string $orderType,
        int $orderId,
        float $amount,
        ?string $description = null
    ): self {
        return self::create([
            'source_type' => self::SOURCE_DRIVER,
            'source_id' => $driverId,
            'order_type' => $orderType,
            'order_id' => $orderId,
            'amount' => $amount,
            'description' => $description ?? 'نسبة ربح من توصيل',
        ]);
    }

    /**
     * Record profit from store order
     */
    public static function recordStoreProfit(
        int $storeId,
        int $orderId,
        float $amount,
        ?string $description = null
    ): self {
        return self::create([
            'source_type' => self::SOURCE_STORE,
            'source_id' => $storeId,
            'order_type' => self::ORDER_TYPE_REGULAR,
            'order_id' => $orderId,
            'amount' => $amount,
            'description' => $description ?? 'نسبة ربح من طلب متجر',
        ]);
    }

    /**
     * Get total profits from drivers
     */
    public static function getTotalDriverProfits(): float
    {
        return (float) self::fromDrivers()->sum('amount');
    }

    /**
     * Get total profits from stores
     */
    public static function getTotalStoreProfits(): float
    {
        return (float) self::fromStores()->sum('amount');
    }

    /**
     * Get total profits (all sources)
     */
    public static function getTotalProfits(): float
    {
        return (float) self::sum('amount');
    }

    /**
     * Get unsettled profits for a specific store
     */
    public static function getUnsettledStoreProfits(int $storeId): float
    {
        return (float) self::forStore($storeId)->unsettled()->sum('amount');
    }

    /**
     * Get total profits for a specific store (all time)
     */
    public static function getTotalStoreProfitsById(int $storeId): float
    {
        return (float) self::forStore($storeId)->sum('amount');
    }

    /**
     * Get settled profits for a specific store
     */
    public static function getSettledStoreProfits(int $storeId): float
    {
        return (float) self::forStore($storeId)->settled()->sum('amount');
    }

    /**
     * Settle all unsettled profits for a specific store
     * 
     * @return int Number of records settled
     */
    public static function settleStoreProfits(int $storeId): int
    {
        return self::forStore($storeId)
            ->unsettled()
            ->update([
                'is_settled' => true,
                'settled_at' => now(),
            ]);
    }

    /**
     * Get store profit statistics
     */
    public static function getStoreProfitStats(int $storeId): array
    {
        return [
            'total_profits' => self::getTotalStoreProfitsById($storeId),
            'unsettled_profits' => self::getUnsettledStoreProfits($storeId),
            'settled_profits' => self::getSettledStoreProfits($storeId),
            'unsettled_count' => self::forStore($storeId)->unsettled()->count(),
        ];
    }
}
