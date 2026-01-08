<?php

namespace App\Models;

use App\Models\Driver;
use App\Models\UserManagement\User;
use App\Models\DiscountManagement\Coupon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'driver_id',
        'coupon_id',
        'coupon_code',
        'subtotal',
        'discount_amount',
        'delivery_fee',
        'total',
        'status',
        'delivery_address',
        'requested_delivery_at',
        'driver_assigned_at',
        'notes',
        'cancellation_reason',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'total' => 'decimal:2',
        'requested_delivery_at' => 'datetime',
        'driver_assigned_at' => 'datetime',
    ];

    /**
     * حالات الطلب
     */
    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_PROCESSING = 'processing';
    const STATUS_READY = 'ready';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * الحالات التي يمكن فيها إلغاء الطلب
     */
    const CANCELLABLE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
    ];

    /* ================= Relations ================= */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /* ================= Accessors ================= */

    /**
     * عدد العناصر في الطلب
     */
    public function getItemsCountAttribute(): int
    {
        return $this->items->sum('quantity');
    }

    /**
     * هل الطلب قابل للإلغاء؟
     */
    public function getIsCancellableAttribute(): bool
    {
        return in_array($this->status, self::CANCELLABLE_STATUSES);
    }

    /**
     * هل تم تطبيق كوبون؟
     */
    public function getHasCouponAttribute(): bool
    {
        return !is_null($this->coupon_id);
    }

    /**
     * هل تم تعيين سائق؟
     */
    public function getHasDriverAttribute(): bool
    {
        return !is_null($this->driver_id);
    }

    /* ================= Scopes ================= */

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [
            self::STATUS_DELIVERED,
            self::STATUS_CANCELLED,
        ]);
    }

    /**
     * الطلبات بدون سائق
     */
    public function scopeWithoutDriver($query)
    {
        return $query->whereNull('driver_id');
    }

    /**
     * طلبات سائق معين
     */
    public function scopeForDriver($query, int $driverId)
    {
        return $query->where('driver_id', $driverId);
    }

    /* ================= Methods ================= */

    /**
     * إلغاء الطلب
     */
    public function cancel(string $reason = null): bool
    {
        if (!$this->is_cancellable) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancellation_reason' => $reason,
        ]);

        return true;
    }

    /**
     * تعيين سائق للطلب
     */
    public function assignDriver(int $driverId): void
    {
        $this->update([
            'driver_id' => $driverId,
            'driver_assigned_at' => now(),
        ]);
    }

    /**
     * تحديث الحالة
     */
    public function updateStatus(string $status): void
    {
        $this->update(['status' => $status]);
    }

    /**
     * جلب العناصر مجمعة حسب المتجر
     */
    public function getItemsByStore(): \Illuminate\Support\Collection
    {
        return $this->items->groupBy('store_id');
    }

    /**
     * الحصول على نص الحالة بالعربية
     */
    public function getStatusTextAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'بانتظار التأكيد',
            self::STATUS_CONFIRMED => 'تم التأكيد',
            self::STATUS_PROCESSING => 'قيد التحضير',
            self::STATUS_READY => 'جاهز للتوصيل',
            self::STATUS_SHIPPED => 'في الطريق',
            self::STATUS_DELIVERED => 'تم التوصيل',
            self::STATUS_CANCELLED => 'ملغي',
            default => $this->status,
        };
    }
}
