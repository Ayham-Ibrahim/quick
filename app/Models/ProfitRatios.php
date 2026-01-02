<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfitRatios extends Model
{
    protected $table = 'profit_ratios';

    protected $fillable = [
        'tag',
        'ratio_name',
        'value',
    ];

    protected $hidden = [
        'tag'
    ];

    // scope for get value by tag
    public function scopeGetValueByTag($query, $tag)
    {
        return $query->where('tag', $tag)->value('value');
    }
}
