<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleType extends Model
{
    protected $fillable = ['type','note'];

    public function drivers(){
        return $this->hasMany(Driver::class, 'vehicle_type_id');
    }
}
