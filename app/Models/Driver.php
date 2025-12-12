<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Driver extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'driver_name',
        'phone',
        'password',

        'driver_image',
        'front_id_image',
        'back_id_image',

        'city',
        'v_location',
        'h_location',

        'vehicle_type_id',
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
     * Define the relationship to the VehicleType model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<VehicleType, Driver>
     */
    public function vehicleType()
    {
        return $this->belongsTo(VehicleType::class, 'vehicle_type_id');
    }
    // Define the relationship to the Wallet model.
    public function wallet()
    {
        return $this->morphOne(Wallet::class, 'owner');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'driver_id');
    }
}
