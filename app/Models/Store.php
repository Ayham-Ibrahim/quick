<?php

namespace App\Models;

use App\Models\Device;
use App\Models\Product;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Categories\Category;
use App\Models\Categories\SubCategory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Store extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'store_name',
        'phone',
        'store_owner_name',
        'password',
        'commercial_register_image',
        'phone_verified_at',
        'store_logo',
        'city',
        'v_location',
        'h_location',
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

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_store');
    }

    public function subCategories()
    {
        return $this->belongsToMany(SubCategory::class, 'store_sub_category');
    }
    public function ratings()
    {
        return $this->morphMany(Rating::class, 'rateable');
    }

    public function averageRating()
    {
        return $this->ratings()->avg('rating') ?? 0;
    }

    /**
     * التحقق من أن رقم الهاتف مفعل
     */
    public function isPhoneVerified(): bool
    {
        return !is_null($this->phone_verified_at);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get the device for the store (single-device only).
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne<Device, Store>
     */
    public function device()
    {
        return $this->morphOne(Device::class, 'owner');
    }

    /**
     * Register or update FCM token.
     * Stores only support single device - previous device will be replaced.
     *
     * @param string $fcmToken
     * @return Device
     */
    public function registerDevice(string $fcmToken): Device
    {
        return Device::registerSingleDevice($this, $fcmToken);
    }

    /**
     * Get FCM token for the store.
     *
     * @return string|null
     */
    public function getFcmToken(): ?string
    {
        return $this->device?->fcm_token;
    }
}
