<?php

namespace App\Models\Categories;

use App\Models\Categories\Category;
use Illuminate\Database\Eloquent\Model;

class SubCategory extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'image',
        'category_id',
    ];

    /**
     * Summary of category
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Category, SubCategory>
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
