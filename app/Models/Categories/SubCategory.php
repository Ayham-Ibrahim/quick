<?php

namespace App\Models\Categories;

use App\Models\Store;
use App\Models\Categories\Category;
use App\Models\Attribute\Attribute;
use App\Models\SubCategoryAttribute;
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
        'price_depends_on_attributes',
        'quantity_depends_on_attributes',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'price_depends_on_attributes' => 'boolean',
        'quantity_depends_on_attributes' => 'boolean',
    ];

    /**
     * Summary of category
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Category, SubCategory>
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function stores()
    {
        return $this->belongsToMany(Store::class, 'store_sub_category');
    }

    /**
     * Get linked attributes through pivot table
     */
    public function linkedAttributes()
    {
        return $this->hasMany(SubCategoryAttribute::class);
    }

    /**
     * Get attributes directly (many-to-many)
     */
    public function attributes()
    {
        return $this->belongsToMany(Attribute::class, 'sub_category_attributes');
    }

    /**
     * Check if this subcategory requires variants
     */
    public function requiresVariants(): bool
    {
        return $this->price_depends_on_attributes || $this->quantity_depends_on_attributes;
    }

    /**
     * Check if subcategory has linked attributes
     */
    public function hasLinkedAttributes(): bool
    {
        return $this->attributes()->exists();
    }
}
