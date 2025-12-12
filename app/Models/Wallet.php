<?php

namespace App\Models;

use App\Models\UserManagement\User;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    protected $fillable = [
        'owner_type',
        'owner_id',
        'wallet_code',
        'balance',
    ];

    public function owner()
    {
        return $this->morphTo();
    }
}
