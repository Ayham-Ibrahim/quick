<?php

namespace App\Models\Attribute;

use Illuminate\Database\Eloquent\Model;

class Attribute extends Model
{
    protected $fillable = [
        'name',
    ];

    public function attributeValues()
    {
        return $this->hasMany(AttributeValue::class);
    }
}
