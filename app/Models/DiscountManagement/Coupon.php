<?php

namespace App\Models\DiscountManagement;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code',
        'type',
        'discount_amount',
        'expiration_duration',
        'usage_limit',
        'times_used',
        'start_at',
        'product_id',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'discount_amount' => 'float',
        'expiration_duration' => 'integer',
        'usage_limit' => 'integer',
        'times_used' => 'integer',
    ];

    protected $appends = ['is_active'];

    public function getIsActiveAttribute(): bool
    {
        $now = now();

        //  before start
        if ($this->start_at && $now->lt($this->start_at)) {
            return false;
        }

        //  expiration at
        $endAt = ($this->start_at ?? $this->created_at)
            ->copy()
            ->addDays($this->expiration_duration);

        //  after expaired
        if ($now->gt($endAt)) {
            return false;
        }

        // usage limit = 0
        if ($this->times_used >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    /**
     * scope for activit coupons only
     * @param mixed $query
     */
    public function scopeCurrentlyActive($query)
    {
        $now = now();

        return $query
            ->where(function ($q) use ($now) {
                $q->whereNull('start_at')
                    ->orWhere('start_at', '<=', $now);
            })
            ->whereRaw(
                "DATE_ADD(start_at, INTERVAL expiration_duration DAY) >= ?",
                [$now]
            )
            ->whereColumn('times_used', '<', 'usage_limit');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /** scopes */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->whereColumn('times_used', '<', 'usage_limit');
    }

    public function isExpired(): bool
    {
        return now()->greaterThan(
            $this->created_at->addDays($this->expiration_duration)
        );
    }
}
