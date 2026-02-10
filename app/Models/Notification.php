<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Notification Model
 * 
 * Represents broadcast notifications sent by admin to multiple target types.
 * Notifications are sent via Firebase Cloud Messaging (FCM).
 */
class Notification extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'content',
        'target_types',
        'status',
        'sent_count',
        'sent_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'target_types' => 'array',
        'sent_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    const STATUS_PENDING = 'pending';
    const STATUS_SENDING = 'sending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    const TARGET_USERS = 'users';
    const TARGET_PROVIDERS = 'providers';
    const TARGET_STORES = 'stores';
    const TARGET_DRIVERS = 'drivers';

    /*
    |--------------------------------------------------------------------------
    | Static Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Get available target types with their Arabic labels.
     *
     * @return array
     */
    public static function getTargetTypes(): array
    {
        return [
            self::TARGET_USERS => 'المستخدمين',
            self::TARGET_PROVIDERS => 'المزودين',
            self::TARGET_STORES => 'المتاجر',
            self::TARGET_DRIVERS => 'السائقين',
        ];
    }

    /**
     * Get available statuses with their Arabic labels.
     *
     * @return array
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'قيد الانتظار',
            self::STATUS_SENDING => 'جاري الإرسال',
            self::STATUS_COMPLETED => 'مكتمل',
            self::STATUS_FAILED => 'فشل',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Get Arabic labels for target types.
     *
     * @return array
     */
    public function getTargetTypesLabelsAttribute(): array
    {
        $allTypes = self::getTargetTypes();
        $labels = [];

        foreach ($this->target_types as $type) {
            if (isset($allTypes[$type])) {
                $labels[] = $allTypes[$type];
            }
        }

        return $labels;
    }

    /**
     * Get Arabic label for status.
     *
     * @return string
     */
    public function getStatusLabelAttribute(): string
    {
        if (is_null($this->status)) {
            return 'غير محدد';
        }

        return self::getStatuses()[$this->status] ?? $this->status;
    }

    /*
    |--------------------------------------------------------------------------
    | Status Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Mark notification as sending.
     *
     * @return bool
     */
    public function markAsSending(): bool
    {
        return $this->update(['status' => self::STATUS_SENDING]);
    }

    /**
     * Mark notification as completed.
     *
     * @param int $sentCount
     * @return bool
     */
    public function markAsCompleted(int $sentCount): bool
    {
        return $this->update([
            'status' => self::STATUS_COMPLETED,
            'sent_count' => $sentCount,
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark notification as failed.
     *
     * @return bool
     */
    public function markAsFailed(): bool
    {
        return $this->update(['status' => self::STATUS_FAILED]);
    }

    /**
     * Increment sent count during batch processing.
     *
     * @param int $count
     * @return bool
     */
    public function incrementSentCount(int $count): bool
    {
        return $this->increment('sent_count', $count);
    }
}
