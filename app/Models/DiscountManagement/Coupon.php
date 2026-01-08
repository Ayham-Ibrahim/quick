<?php

namespace App\Models\DiscountManagement;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'store_id',
        'code',
        'type',
        'amount',
        'usage_limit_total',
        'usage_limit_per_user',
        'start_at',
        'end_at',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'amount' => 'float',
        'usage_limit_total' => 'integer',
        'usage_limit_per_user' => 'integer'
    ];

    protected $appends = ['is_active', 'total_usage'];

    /* ================= Relations ================= */

    /**
     * المتجر الذي يتبع له الكوبون
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'coupon_products')
            ->withTimestamps();
    }

    public function usages()
    {
        return $this->hasMany(CouponUsage::class);
    }

    /* ================= Accessors ================= */

    public function getIsActiveAttribute(): bool
    {
        $now = now();

        if ($this->start_at && $now->lt($this->start_at)) {
            return false;
        }

        if ($this->end_at && $now->gt($this->end_at)) {
            return false;
        }

        if ($this->total_usage >= $this->usage_limit_total) {
            return false;
        }

        return true;
    }

    public function getTotalUsageAttribute(): int
    {
        // lazy load safe
        return $this->usages_count
            ?? $this->usages()->count();

        // استخدم Coupon::withCount('usages')->get();
    }

    /* ================= Business Logic ================= */

    public function canBeUsedBy(int $userId): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->usedByUser($userId) < $this->usage_limit_per_user;
    }

    public function usedByUser(int $userId): int
    {
        return $this->usages()
            ->where('user_id', $userId)
            ->count();
    }

    public function isExpired(): bool
    {
        return $this->end_at
            ? now()->gt($this->end_at)
            : false;
    }

    /* ================= Scopes ================= */

    /**
     * Coupons currently valid by date & total usage
     */
    public function scopeActive($query)
    {
        $now = now();

        return $query
            ->withCount('usages')
            ->where(function ($q) use ($now) {
                $q->whereNull('start_at')
                    ->orWhere('start_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_at')
                    ->orWhere('end_at', '>=', $now);
            })
            ->whereColumn('usages_count', '<', 'usage_limit_total');
    }
}
