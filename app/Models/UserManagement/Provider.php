<?php

namespace App\Models\UserManagement;

use App\Models\Device;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
class Provider extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'provider_name',
        'market_name',
        'v_location',
        'h_location',
        'phone_verified_at',
        'phone',
        'password',
        'city'
        // 'walet_id'
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
        ];
    }

      /**
     * التحقق من أن رقم الهاتف مفعل
     */
    public function isPhoneVerified(): bool
    {
        return !is_null($this->phone_verified_at);
    }

    public function transactions(){
        return $this->hasMany(Transaction::class, 'provider_id');
    }

    /**
     * Get the device for the provider (single-device only).
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne<Device, Provider>
     */
    public function device()
    {
        return $this->morphOne(Device::class, 'owner');
    }

    /**
     * Register or update FCM token.
     * Providers only support single device - previous device will be replaced.
     *
     * @param string $fcmToken
     * @return Device
     */
    public function registerDevice(string $fcmToken): Device
    {
        return Device::registerSingleDevice($this, $fcmToken);
    }

    /**
     * Get FCM token for the provider.
     *
     * @return string|null
     */
    public function getFcmToken(): ?string
    {
        return $this->device?->fcm_token;
    }
}
