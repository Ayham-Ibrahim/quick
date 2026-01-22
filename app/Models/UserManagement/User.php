<?php

namespace App\Models\UserManagement;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Models\Device;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'phone',
        'avatar',
        'gender',
        'city',
        'v_location',
        'h_location',
        'password',
        'is_admin',
        'phone_verified_at',
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
            'is_admin' => 'boolean',
        ];
    }

    /**
     * التحقق من أن رقم الهاتف مفعل
     */
    public function isPhoneVerified(): bool
    {
        return !is_null($this->phone_verified_at);
    }

    /**
     * Define the relationship to the Wallet model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne<Wallet, User>
     */
    public function wallet()
    {
        return $this->morphOne(Wallet::class, 'owner');
    }

    /**
     * Get all devices for the user (multi-device support).
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany<Device, User>
     */
    public function devices()
    {
        return $this->morphMany(Device::class, 'owner');
    }

    /**
     * Register or update FCM token for a device.
     * Users support multiple devices.
     *
     * @param string $fcmToken
     * @return Device
     */
    public function registerDevice(string $fcmToken): Device
    {
        return Device::registerMultiDevice($this, $fcmToken);
    }

    /**
     * Get all FCM tokens for the user.
     *
     * @return \Illuminate\Support\Collection<string>
     */
    public function getFcmTokens()
    {
        return Device::getTokens($this);
    }
}
