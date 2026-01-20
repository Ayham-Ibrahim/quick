<?php

namespace App\Models;

use App\Models\Driver;
use App\Models\UserManagement\User;
use App\Models\DiscountManagement\Coupon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * نموذج الطلب العادي (منتجات من المتاجر)
 * 
 * ═══════════════════════════════════════════════════════════════════════
 * مسار الحالات المبسط (Simplified Order Status Flow):
 * ═══════════════════════════════════════════════════════════════════════
 * 
 *   pending ──────────► shipping ──────────► delivered
 *      │                   │
 *      │                   │
 *      ▼                   ▼
 *   cancelled          cancelled (مع سبب)
 * 
 * الحالات الأربعة:
 * ─────────────────
 * 1. pending    = معلق (بانتظار قبول سائق)
 * 2. shipping   = قيد التوصيل
 * 3. delivered  = تم التسليم
 * 4. cancelled  = ملغي/فشل التسليم (مع سبب)
 * 
 * ═══════════════════════════════════════════════════════════════════════
 */
class Order extends Model
{
    /* ═══════════════════════════════════════════════════════════════════
     * الثوابت - Constants
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * الحالات الأربعة الأساسية
     */
    const STATUS_PENDING = 'pending';       // معلق - بانتظار قبول سائق
    const STATUS_SHIPPING = 'shipping';     // قيد التوصيل
    const STATUS_DELIVERED = 'delivered';   // تم التسليم
    const STATUS_CANCELLED = 'cancelled';   // ملغي/فشل التسليم (مع سبب)

    /**
     * مدة انتظار قبول السائق (بالدقائق)
     */
    const DRIVER_CONFIRMATION_TIMEOUT_MINUTES = 5;

    /* ═══════════════════════════════════════════════════════════════════
     * الخصائص - Properties
     * ═══════════════════════════════════════════════════════════════════ */

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
        'confirmation_expires_at',
        'delivery_address',
        'requested_delivery_at',
        'is_immediate_delivery',
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
        'confirmation_expires_at' => 'datetime',
        'driver_assigned_at' => 'datetime',
        'is_immediate_delivery' => 'boolean',
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

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /* ═══════════════════════════════════════════════════════════════════
     * المُحَصِّلات - Accessors
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * عدد العناصر في الطلب
     */
    public function getItemsCountAttribute(): int
    {
        return $this->items->sum('quantity');
    }

    /**
     * هل الطلب قابل للإلغاء؟ (فقط في حالة معلق)
     */
    public function getIsCancellableAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING;
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

    /**
     * هل انتهت صلاحية انتظار السائق؟
     */
    public function getIsConfirmationExpiredAttribute(): bool
    {
        if (!$this->confirmation_expires_at) {
            return true;
        }
        return now()->gt($this->confirmation_expires_at);
    }

    /**
     * هل يمكن إعادة إرسال الإشعارات للسائقين؟
     * (الطلب معلق وبدون سائق وانتهت الصلاحية)
     */
    public function getCanResendToDriversAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING
            && !$this->has_driver
            && $this->is_confirmation_expired;
    }

    /**
     * هل الطلب متاح للسائقين للقبول؟
     */
    public function getIsAvailableForDriverAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING
            && !$this->has_driver
            && !$this->is_confirmation_expired;
    }

    /**
     * هل يمكن للسائق إلغاء التوصيل؟
     * ✅ فقط للطلبات المجدولة (غير الفورية) في حالة shipping
     * ❌ الطلبات الفورية لا يمكن إلغاؤها من السائق
     */
    public function getCanDriverCancelDeliveryAttribute(): bool
    {
        return $this->status === self::STATUS_SHIPPING
            && $this->has_driver
            && !$this->is_immediate_delivery; // مجدول فقط
    }

    /**
     * هل يمكن للمستخدم إلغاء الطلب؟
     * ✅ فقط في حالة pending
     */
    public function getCanUserCancelAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * هل يمكن للإدارة إلغاء الطلب؟
     * ✅ في أي حالة ما عدا delivered أو cancelled
     */
    public function getCanAdminCancelAttribute(): bool
    {
        return !in_array($this->status, [self::STATUS_DELIVERED, self::STATUS_CANCELLED]);
    }

    /**
     * هل يمكن إعادة طلب الطلبية؟
     * ✅ فقط بعد التسليم
     */
    public function getCanReorderAttribute(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    /**
     * الحصول على نص الحالة بالعربية
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
     * النطاقات - Scopes
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * فلترة حسب الحالة
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * الطلبات المعلقة (بانتظار سائق)
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
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

    /**
     * الطلبات المتاحة للسائقين (معلقة بدون سائق ولم تنته الصلاحية)
     */
    public function scopeAvailableForDrivers($query)
    {
        return $query->where('status', self::STATUS_PENDING)
            ->whereNull('driver_id');
    }

    /**
     * الطلبات التي انتهت صلاحية انتظار السائق
     */
    public function scopeConfirmationExpired($query)
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

    /* ═══════════════════════════════════════════════════════════════════
     * العمليات - Methods
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * إلغاء الطلب (من المستخدم أو النظام)
     * 
     * @param string|null $reason سبب الإلغاء
     * @return bool نجاح العملية
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
     * تعيين سائق للطلب وبدء التوصيل
     * 
     * @param int $driverId معرف السائق
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
     * تأكيد استلام الطلب (تم التوصيل بنجاح)
     */
    public function markAsDelivered(): void
    {
        $this->update([
            'status' => self::STATUS_DELIVERED,
        ]);
    }

    /**
     * تسجيل فشل/إلغاء التوصيل (من السائق)
     * 
     * @param string $reason سبب الفشل/الإلغاء
     */
    public function markAsCancelled(string $reason): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancellation_reason' => $reason,
        ]);
    }

    /**
     * إعادة المحاولة بعد الإلغاء (إرسال لسائق جديد)
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
     * تحديث الحالة
     * 
     * @param string $status الحالة الجديدة
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
     * تجديد صلاحية انتظار السائق (إعادة إرسال للسائقين)
     */
    public function resendToDrivers(): void
    {
        $this->update([
            'confirmation_expires_at' => now()->addMinutes(self::DRIVER_CONFIRMATION_TIMEOUT_MINUTES),
        ]);
    }

    /* ═══════════════════════════════════════════════════════════════════
     * المساعدات الثابتة - Static Helpers
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * الحصول على جميع الحالات المتاحة
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
