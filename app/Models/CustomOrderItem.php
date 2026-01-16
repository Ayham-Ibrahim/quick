<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomOrderItem extends Model
{
    protected $fillable = [
        'custom_order_id',
        'description',
        'pickup_address',
        'pickup_lat',
        'pickup_lng',
        'order_index',
    ];

    protected $casts = [
        'pickup_lat' => 'decimal:7',
        'pickup_lng' => 'decimal:7',
        'order_index' => 'integer',
    ];

    /* ================= Relations ================= */

    public function customOrder(): BelongsTo
    {
        return $this->belongsTo(CustomOrder::class);
    }
}
