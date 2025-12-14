<?php

namespace App\Models;

use App\Models\UserManagement\Provider;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'provider_id',
        'driver_id',
        'amount',
    ];

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}
