<?php

namespace App\Models\DiscountManagement;

use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    protected $fillable = [
        'rate',
        'purchasing_value'
    ];

    protected $casts = [
        'rate' => 'integer',
        'purchasing_value' => 'float',
    ];
}
