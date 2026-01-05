<?php

namespace App\Services\Products;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Service;
use App\Services\FileStorage;
use App\Models\Categories\SubCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Exceptions\HttpResponseException;

class ProductService extends Service
{
    /**
     * add new product to the database
     * @param mixed $data
     * @return Product|null
     * @throws \Exception If an error occurs during the transaction.
     */
    public function storeProduct($data)
    {
        try {
            DB::beginTransaction();

            // Validate subcategory requirements
            $subCategory = SubCategory::with('attributes')->find($data['sub_category_id']);
            $this->validateSubCategoryRequirements($subCategory, $data);

            $product = Product::create([
                'store_id' => $data['store_id'],
                'name' => $data['name'],
                'description' => $data['description'],
                'quantity' => $subCategory->quantity_depends_on_attributes ? null : ($data['quantity'] ?? null),
                'current_price' => $subCategory->price_depends_on_attributes ? null : ($data['current_price'] ?? null),
                'previous_price' => $data['previous_price'] ?? null,
                'sub_category_id' => $data['sub_category_id'],
                'is_accepted' => false, // Assuming new products need admin approval
            ]);

            $this->storeProductImages($product, $data['images']);

            // Store product variants if provided
            if (!empty($data['variants'])) {
                $this->storeProductVariants($product, $data['variants']);
            }

            DB::commit();

            return $product->load(['images', 'variants.attributes.attribute', 'variants.attributes.value']);
        } catch (\Throwable $th) {
            Log::error($th);
            DB::rollBack();
            if ($th instanceof HttpResponseException) {
                throw $th;
            }
            $this->throwExceptionJson();
        }
    }

    /**
     * Validate that product data meets subcategory requirements
     */
    protected function validateSubCategoryRequirements(SubCategory $subCategory, array $data): void
    {
        // If subcategory requires variants (price or quantity depends on attributes)
        if ($subCategory->requiresVariants()) {
            if (empty($data['variants'])) {
                $this->throwExceptionJson(
                    'هذه الفئة الفرعية تتطلب إضافة متغيرات للمنتج (variants) مع الخصائص',
                    422
                );
            }

            // Validate each variant has at least one attribute
            foreach ($data['variants'] as $index => $variant) {
                if (empty($variant['attributes'])) {
                    $this->throwExceptionJson(
                        "المتغير رقم " . ($index + 1) . " يجب أن يحتوي على خاصية واحدة على الأقل",
                        422
                    );
                }
            }
        }

        // If price depends on attributes, variants must have prices
        if ($subCategory->price_depends_on_attributes && !empty($data['variants'])) {
            foreach ($data['variants'] as $index => $variant) {
                if (!isset($variant['price']) || $variant['price'] === null) {
                    $this->throwExceptionJson(
                        "المتغير رقم " . ($index + 1) . " يجب أن يحتوي على سعر",
                        422
                    );
                }
            }
        }

        // If quantity depends on attributes, variants must have stock
        if ($subCategory->quantity_depends_on_attributes && !empty($data['variants'])) {
            foreach ($data['variants'] as $index => $variant) {
                if (!isset($variant['stock_quantity'])) {
                    $this->throwExceptionJson(
                        "المتغير رقم " . ($index + 1) . " يجب أن يحتوي على كمية",
                        422
                    );
                }
            }
        }

        // Validate that variant attributes belong to the linked subcategory attributes
        if (!empty($data['variants']) && $subCategory->hasLinkedAttributes()) {
            $linkedAttributeIds = $subCategory->attributes()->pluck('attributes.id')->toArray();

            foreach ($data['variants'] as $index => $variant) {
                foreach ($variant['attributes'] ?? [] as $attr) {
                    if (!in_array($attr['attribute_id'], $linkedAttributeIds)) {
                        $this->throwExceptionJson(
                            "المتغير رقم " . ($index + 1) . " يحتوي على خاصية غير مرتبطة بهذه الفئة الفرعية",
                            422
                        );
                    }
                }
            }
        }
    }

    /**
     * Updates an existing product along with its images.
     * @param mixed $data
     * @param \App\Models\Product $product
     * @return Product|null
     * @throws \Exception If an error occurs during the transaction.
     */
    public function updateProduct($data, Product $product)
    {
        try {
            DB::beginTransaction();

            $product->update([
                'name' => $data['name'] ?? $product->name,
                'description' => $data['description'] ?? $product->description,
                'quantity' => $data['quantity'] ?? $product->quantity,
                'current_price' => $data['current_price'] ?? $product->current_price,
                'previous_price' => $data['previous_price'] ?? $product->previous_price,
                'sub_category_id' => $data['sub_category_id'] ?? $product->sub_category_id,
            ]);

            if (isset($data['images'])) {
                $product->images()->delete();
                $this->storeProductImages($product, $data['images']);
            }

            // Handle variant deletions
            if (!empty($data['deleted_variant_ids'])) {
                ProductVariant::whereIn('id', $data['deleted_variant_ids'])
                    ->where('product_id', $product->id)
                    ->delete();
            }

            // Handle variant updates/creates
            if (isset($data['variants'])) {
                $this->updateProductVariants($product, $data['variants']);
            }

            DB::commit();
            return $product->load(['images', 'variants.attributes.attribute', 'variants.attributes.value']);
        } catch (\Throwable $th) {
            Log::error($th);
            DB::rollBack();
            if ($th instanceof HttpResponseException) {
                throw $th;
            }
            $this->throwExceptionJson();
        }
    }

    /**
     * Stores images for the specified product.
     *
     * @param \App\Models\Product $product The product instance.
     * @param array $images The array of image data, each containing a file.
     */
    protected function storeProductImages(Product $product, array $images)
    {
        $productImages = collect($images)->map(function ($imageItem) {

            // Expecting: [ "file" => UploadedFile ]
            $file = $imageItem['file'] ?? null;

            if ($file instanceof \Illuminate\Http\UploadedFile) {
                return [
                    'image' => FileStorage::storeFile($file, 'Product', 'img'),
                ];
            }

            Log::error('Invalid image file type:', ['image' => $imageItem]);
            return null;
        })->filter()->toArray();

        if (!empty($productImages)) {
            $product->images()->createMany($productImages);
        }
    }


    /**
     * Store product variants with their attributes
     *
     * @param Product $product
     * @param array $variants
     */
    protected function storeProductVariants(Product $product, array $variants): void
    {
        foreach ($variants as $variantData) {
            $variant = $product->variants()->create([
                'sku' => $variantData['sku'] ?? $this->generateSku($product),
                'price' => $variantData['price'],
                'stock_quantity' => $variantData['stock_quantity'],
                'is_active' => $variantData['is_active'] ?? true,
            ]);

            // Store variant attributes (Color: Red, Size: XL, etc.)
            foreach ($variantData['attributes'] as $attribute) {
                $variant->attributes()->create([
                    'attribute_id' => $attribute['attribute_id'],
                    'attribute_value_id' => $attribute['attribute_value_id'],
                ]);
            }
        }
    }

    /**
     * Update product variants (create new, update existing)
     *
     * @param Product $product
     * @param array $variants
     */
    protected function updateProductVariants(Product $product, array $variants): void
    {
        foreach ($variants as $variantData) {
            if (isset($variantData['id'])) {
                // Update existing variant
                $variant = ProductVariant::find($variantData['id']);
                if ($variant && $variant->product_id === $product->id) {
                    $variant->update([
                        'sku' => $variantData['sku'] ?? $variant->sku,
                        'price' => $variantData['price'],
                        'stock_quantity' => $variantData['stock_quantity'],
                        'is_active' => $variantData['is_active'] ?? $variant->is_active,
                    ]);

                    // Update attributes - delete old and create new
                    $variant->attributes()->delete();
                    foreach ($variantData['attributes'] as $attribute) {
                        $variant->attributes()->create([
                            'attribute_id' => $attribute['attribute_id'],
                            'attribute_value_id' => $attribute['attribute_value_id'],
                        ]);
                    }
                }
            } else {
                // Create new variant
                $variant = $product->variants()->create([
                    'sku' => $variantData['sku'] ?? $this->generateSku($product),
                    'price' => $variantData['price'],
                    'stock_quantity' => $variantData['stock_quantity'],
                    'is_active' => $variantData['is_active'] ?? true,
                ]);

                foreach ($variantData['attributes'] as $attribute) {
                    $variant->attributes()->create([
                        'attribute_id' => $attribute['attribute_id'],
                        'attribute_value_id' => $attribute['attribute_value_id'],
                    ]);
                }
            }
        }
    }

    /**
     * Generate a unique SKU for a product variant
     *
     * @param Product $product
     * @return string
     */
    protected function generateSku(Product $product): string
    {
        // Remove non-ASCII characters and use only alphanumeric chars for SKU prefix
        $cleanName = preg_replace('/[^A-Za-z0-9]/', '', $product->name);
        $prefix = !empty($cleanName) 
            ? strtoupper(substr($cleanName, 0, 3)) 
            : 'PRD';
        $timestamp = now()->format('ymdHis');
        $random = strtoupper(substr(uniqid(), -4));

        return "{$prefix}-{$product->id}-{$timestamp}-{$random}";
    }

    /**
     * Decrease stock quantity for a specific variant
     *
     * @param int $variantId
     * @param int $quantity
     * @return bool
     * @throws \Exception
     */
    public function decreaseVariantStock(int $variantId, int $quantity): bool
    {
        $variant = ProductVariant::lockForUpdate()->find($variantId);

        if (!$variant) {
            $this->throwExceptionJson('المتغير غير موجود', 404);
        }

        if ($variant->stock_quantity < $quantity) {
            $this->throwExceptionJson('الكمية المطلوبة غير متوفرة في المخزون', 400);
        }

        $variant->decrement('stock_quantity', $quantity);

        return true;
    }

    /**
     * Increase stock quantity for a specific variant (e.g., order cancellation)
     *
     * @param int $variantId
     * @param int $quantity
     * @return bool
     */
    public function increaseVariantStock(int $variantId, int $quantity): bool
    {
        $variant = ProductVariant::find($variantId);

        if (!$variant) {
            $this->throwExceptionJson('المتغير غير موجود', 404);
        }

        $variant->increment('stock_quantity', $quantity);

        return true;
    }

    /**
     * Check if variant has enough stock
     *
     * @param int $variantId
     * @param int $quantity
     * @return bool
     */
    public function checkVariantStock(int $variantId, int $quantity): bool
    {
        $variant = ProductVariant::find($variantId);

        if (!$variant) {
            return false;
        }

        return $variant->is_active && $variant->stock_quantity >= $quantity;
    }

    /**
     * Get product with full variant details
     *
     * @param int $productId
     * @return Product|null
     */
    public function getProductWithVariants(int $productId): ?Product
    {
        return Product::with([
            'store:id,store_name,store_logo',
            'subCategory:id,name,category_id',
            'subCategory.category:id,name',
            'images',
            'variants' => function ($query) {
                $query->where('is_active', true);
            },
            'variants.attributes.attribute',
            'variants.attributes.value',
            'ratings'
        ])->find($productId);
    }
}

