<?php

namespace App\Models;

use App\Models\Driver;
use App\Models\UserManagement\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Custom Order model ("Request Anything")
 * 
 * ═══════════════════════════════════════════════════════════════════════
 * Simplified Custom Order Status Flow:
 * ═══════════════════════════════════════════════════════════════════════
 * 
 *   pending ──────────► shipping ──────────► delivered
 *      │                   │
 *      │                   │
 *      ▼                   ▼
 *   cancelled          cancelled (with reason)
 * 
 * The four statuses:
 * ─────────────────
 * 1. pending    = waiting for driver to accept
 * 2. shipping   = delivering
 * 3. delivered  = delivered
 * 4. cancelled  = cancelled/failed delivery (with reason)
 * 
 * ═══════════════════════════════════════════════════════════════════════
 */
class CustomOrder extends Model
{
    /* ═══════════════════════════════════════════════════════════════════
     * Constants
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * The four core statuses
     */
    const STATUS_PENDING = 'pending';       // pending - waiting for driver to accept
    const STATUS_SHIPPING = 'shipping';     // shipping - delivering
    const STATUS_DELIVERED = 'delivered';   // delivered - delivered
    const STATUS_CANCELLED = 'cancelled';   // cancelled - cancelled/failed delivery (with reason)

    /**
     * Driver confirmation timeout (minutes)
     */
    const DRIVER_CONFIRMATION_TIMEOUT_MINUTES = 5;

    /* ═══════════════════════════════════════════════════════════════════
     * Properties
     * ═══════════════════════════════════════════════════════════════════ */

    protected $fillable = [
        'user_id',
        'driver_id',
        'delivery_fee',
        'distance_km',
        'status',
        'delivery_address',
        'delivery_lat',
        'delivery_lng',
        'is_immediate',
        'scheduled_at',
        'confirmation_expires_at',
        'driver_assigned_at',
        'notes',
        'cancellation_reason',
    ];

    protected $casts = [
        'delivery_fee' => 'decimal:2',
        'distance_km' => 'decimal:2',
        'delivery_lat' => 'decimal:7',
        'delivery_lng' => 'decimal:7',
        'is_immediate' => 'boolean',
        'scheduled_at' => 'datetime',
        'confirmation_expires_at' => 'datetime',
        'driver_assigned_at' => 'datetime',
    ];

    /* ═══════════════════════════════════════════════════════════════════
     * العلاقات - Relations
     * ═══════════════════════════════════════════════════════════════════ */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CustomOrderItem::class)->orderBy('order_index');
    }

    /* ═══════════════════════════════════════════════════════════════════
     * Accessors
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * Number of items in the order
     */
    public function getItemsCountAttribute(): int
    {
        return $this->items->count();
    }

    /**
     * Is the order cancellable? (only when pending)
     */
    public function getIsCancellableAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Has a driver been assigned?
     */
    public function getHasDriverAttribute(): bool
    {
        return !is_null($this->driver_id);
    }

    /**
     * Is the confirmation request expired?
     */
    public function getIsConfirmationExpiredAttribute(): bool
    {
        if (!$this->confirmation_expires_at) {
            return true;
        }
        return now()->gt($this->confirmation_expires_at);
    }

    /**
     * Can the order be resent to drivers?
     * (pending, without driver, and confirmation expired)
     */
    public function getCanResendToDriversAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING
            && !$this->has_driver
            && $this->is_confirmation_expired;
    }

    /**
     * Is the order available for drivers to accept?
     */
    public function getIsAvailableForDriverAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING
            && !$this->has_driver
            && !$this->is_confirmation_expired;
    }

    /**
     * Can the driver cancel delivery?
     * ✅ only for scheduled (non-immediate) orders in shipping
     * ❌ immediate orders cannot be cancelled by the driver
     */
    public function getCanDriverCancelDeliveryAttribute(): bool
    {
        return $this->status === self::STATUS_SHIPPING
            && $this->has_driver
            && !$this->is_immediate; // scheduled only
    }

    /**
     * Can the user cancel the order?
     * ✅ only when pending
     */
    public function getCanUserCancelAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Can the admin cancel the order?
     * ✅ in any state except delivered or cancelled
     */
    public function getCanAdminCancelAttribute(): bool
    {
        return !in_array($this->status, [self::STATUS_DELIVERED, self::STATUS_CANCELLED]);
    }

    /**
     * Get status text (Arabic)
     */
    public function getStatusTextAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'معلق',
            self::STATUS_SHIPPING => 'قيد التوصيل',
            self::STATUS_DELIVERED => 'تم التسليم',
            self::STATUS_CANCELLED => 'ملغي',
            default => $this->status,
        };
    }

    /* ═══════════════════════════════════════════════════════════════════
     * Scopes
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * Filter by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Pending orders (waiting for driver)
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Orders available for drivers (pending without driver and not expired)
     */
    public function scopeAvailableForDrivers($query)
    {
        return $query->where('status', self::STATUS_SHIPPING);
            // ->('driver_id');
    }

    /**
     * Orders with expired confirmation
     */
    public function scopeExpired($query)
    {
        return $query->where('status', self::STATUS_PENDING)
            ->whereNull('driver_id')
            ->where('confirmation_expires_at', '<=', now());
    }

    /**
     * طلبات سائق معين
     */
    public function scopeForDriver($query, int $driverId)
    {
        return $query->where('driver_id', $driverId);
    }

    /**
     * الطلبات قيد التوصيل لسائق معين
     */
    public function scopeActiveForDriver($query, int $driverId)
    {
        return $query->where('driver_id', $driverId)
            ->where('status', self::STATUS_SHIPPING);
    }

    /**
     * الطلبات النشطة (غير منتهية أو ملغية)
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [
            self::STATUS_DELIVERED,
            self::STATUS_CANCELLED,
        ]);
    }

    /* ═══════════════════════════════════════════════════════════════════
     * Methods
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * Cancel order (by user or system)
     * 
     * @param string|null $reason Cancellation reason
     * @return bool Success
     */
    public function cancel(?string $reason = null): bool
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
     * Assign driver to the order and start shipping
     * 
     * @param int $driverId Driver id
     */
    public function assignDriverAndStartShipping(int $driverId): void
    {
        $this->update([
            'driver_id' => $driverId,
            'driver_assigned_at' => now(),
            'status' => self::STATUS_SHIPPING,
        ]);
    }

    /**
     * Mark order as delivered (successfully delivered)
     */
    public function markAsDelivered(): void
    {
        $this->update([
            'status' => self::STATUS_DELIVERED,
        ]);
    }

    /**
     * Mark delivery failure/cancellation (by driver)
     * 
     * @param string $reason Failure/cancellation reason
     */
    public function markAsCancelled(string $reason): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancellation_reason' => $reason,
        ]);
    }

    /**
     * Retry delivery after cancellation (send to a new driver)
     */
    public function retryDelivery(): void
    {
        $this->update([
            'driver_id' => null,
            'driver_assigned_at' => null,
            'cancellation_reason' => null,
            'status' => self::STATUS_PENDING,
            'confirmation_expires_at' => now()->addMinutes(self::DRIVER_CONFIRMATION_TIMEOUT_MINUTES),
        ]);
    }

    /**
     * Update status
     * 
     * @param string $status New status
     */
    public function updateStatus(string $status): void
    {
        $this->update(['status' => $status]);
    }

    /**
     * Renew driver confirmation expiry (resend to drivers)
     */
    public function resendToDrivers(): void
    {
        $this->update([
            'confirmation_expires_at' => now()->addMinutes(self::DRIVER_CONFIRMATION_TIMEOUT_MINUTES),
        ]);
    }

    /* ═══════════════════════════════════════════════════════════════════
     * Static Helpers
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * Get all available statuses
     */
    public static function getAllStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_SHIPPING,
            self::STATUS_DELIVERED,
            self::STATUS_CANCELLED,
        ];
    }
}
