<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'image'
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        // Delete the image file when the model is deleted
        static::deleted(function (ProductImage $productImage) {
            Storage::disk('public')->delete($productImage->image);
        });

        // Delete the old image file when the model is updated
        static::updating(function (ProductImage $productImage) {
            $originalImage = $productImage->getOriginal('image');
            if ($originalImage !== $productImage->image) {
                Storage::disk('public')->delete($originalImage);
            }
        });
    }

    /**
     * Get the full URL for the product image
     *
     * @return string|null
     */
    public function getImageAttribute()
    {
        return $this->attributes['image'] ? asset($this->attributes['image']) : null;
    }

    /**
     * Get the product that owns the ProductImage
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
