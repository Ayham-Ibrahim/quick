<?php

namespace App\Models;

use App\Models\UserManagement\User;
use App\Models\UserManagement\Provider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Device Model
 * 
 * Stores FCM tokens for push notifications.
 * Uses polymorphic relationship to support multiple owner types:
 * - User: Can have multiple devices (multi-device login allowed)
 * - Driver: Single device only (one device per account)
 * - Provider: Single device only (one device per account)
 * - Store: Single device only (one device per account)
 */
class Device extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'owner_type',
        'owner_id',
        'fcm_token',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Get the owning model (User, Driver, Provider, or Store).
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function owner()
    {
        return $this->morphTo();
    }

    /*
    |--------------------------------------------------------------------------
    | Static Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Register or update FCM token for a single-device owner (Driver, Provider, Store, Admin).
     * Removes any existing devices and creates a new one.
     *
     * @param \Illuminate\Database\Eloquent\Model $owner
     * @param string $fcmToken
     * @return Device
     */
    public static function registerSingleDevice($owner, string $fcmToken): Device
    {
        // Remove all existing devices for this owner
        self::where('owner_type', get_class($owner))
            ->where('owner_id', $owner->id)
            ->delete();

        // Create new device record
        return self::create([
            'owner_type' => get_class($owner),
            'owner_id' => $owner->id,
            'fcm_token' => $fcmToken,
        ]);
    }

    /**
     * Register or update FCM token for a multi-device owner (User).
     * Updates existing device if token matches, otherwise creates new one.
     *
     * @param \Illuminate\Database\Eloquent\Model $owner
     * @param string $fcmToken
     * @return Device
     */
    public static function registerMultiDevice($owner, string $fcmToken): Device
    {
        // Check if token already exists for this owner (update it)
        $existingDevice = self::where('owner_type', get_class($owner))
            ->where('owner_id', $owner->id)
            ->where('fcm_token', $fcmToken)
            ->first();

        if ($existingDevice) {
            $existingDevice->touch(); // Update updated_at
            return $existingDevice;
        }

        // Create new device record
        return self::create([
            'owner_type' => get_class($owner),
            'owner_id' => $owner->id,
            'fcm_token' => $fcmToken,
        ]);
    }

    /**
     * Remove a device by FCM token.
     *
     * @param \Illuminate\Database\Eloquent\Model $owner
     * @param string $fcmToken
     * @return bool
     */
    public static function removeByToken($owner, string $fcmToken): bool
    {
        return self::where('owner_type', get_class($owner))
            ->where('owner_id', $owner->id)
            ->where('fcm_token', $fcmToken)
            ->delete() > 0;
    }

    /**
     * Remove all devices for an owner (used on logout from all devices).
     *
     * @param \Illuminate\Database\Eloquent\Model $owner
     * @return int Number of deleted devices
     */
    public static function removeAllDevices($owner): int
    {
        return self::where('owner_type', get_class($owner))
            ->where('owner_id', $owner->id)
            ->delete();
    }

    /**
     * Get all FCM tokens for an owner.
     *
     * @param \Illuminate\Database\Eloquent\Model $owner
     * @return \Illuminate\Support\Collection<string>
     */
    public static function getTokens($owner)
    {
        return self::where('owner_type', get_class($owner))
            ->where('owner_id', $owner->id)
            ->pluck('fcm_token');
    }
}
