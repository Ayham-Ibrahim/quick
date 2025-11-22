<?php

namespace App\Models\Categories;

use App\Models\Store;
use App\Models\Categories\SubCategory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'image',
    ];

    /**
     * Summary of subCategories
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<SubCategory, Category>
     */
    public function subCategories()
    {
        return $this->hasMany(SubCategory::class);
    }

    public function stores()
    {
        return $this->belongsToMany(Store::class, 'category_store');
    }
}
