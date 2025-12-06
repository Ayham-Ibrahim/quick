<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OTPCode extends Model
{
    protected $table = 'otp_codes';
    protected $fillable = [
        'phone',
        'code',
        'type',
        'expires_at',
        'attempts'
    ];

    protected $casts = [
        'expires_at' => 'datetime'
    ];
}
