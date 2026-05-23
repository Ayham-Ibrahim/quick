<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfitRatios extends Model
{
    public const TAG_EXCHANGE_RATE = 'exchange_rate';

    protected $table = 'profit_ratios';

    protected $fillable = [
        'tag',
        'ratio_name',
        'value',
    ];

    protected $hidden = [
        'tag'
    ];

    public static function getValueByTag(string $tag): mixed
    {
        return static::query()
            ->where('tag', $tag)
            ->value('value');
    }

    // scope for get value by tag
    public function scopeGetValueByTag($query, $tag)
    {
        return $query->where('tag', $tag)->value('value');
    }
}
