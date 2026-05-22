<?php

namespace App\Services\Products;

use App\Models\Categories\SubCategory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\FileStorage;
use App\Services\Pricing\DynamicPricingService;
use App\Services\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Exceptions\HttpResponseException;

class ProductService extends Service
{
    public function __construct(
        protected DynamicPricingService $dynamicPricingService
    ) {
    }

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
            $productPricingPayload = $this->dynamicPricingService->prepareProductPricingPayload(
                $data,
                !$subCategory->price_depends_on_attributes
            );

            $product = Product::create([
                'store_id' => $data['store_id'],
                'name' => $data['name'],
                'description' => $data['description'],
                'quantity' => $subCategory->quantity_depends_on_attributes ? null : ($data['quantity'] ?? null),
                'current_price' => $productPricingPayload['current_price'],
                'base_price_usd' => $productPricingPayload['base_price_usd'],
                'sync_enabled' => $productPricingPayload['sync_enabled'],
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

            $targetSubCategory = isset($data['sub_category_id'])
                ? SubCategory::with('attributes')->find($data['sub_category_id'])
                : $product->subCategory;

            $this->validateSubCategoryRequirements($targetSubCategory, array_merge([
                'variants' => $data['variants'] ?? $product->variants()->with('attributes')->get()->toArray(),
            ], $data));

            $productPricingPayload = $this->dynamicPricingService->prepareProductPricingPayload(
                $data,
                !$targetSubCategory->price_depends_on_attributes,
                $product
            );

            $product->update([
                'name' => $data['name'] ?? $product->name,
                'description' => $data['description'] ?? $product->description,
                'quantity' => $data['quantity'] ?? $product->quantity,
                'current_price' => $productPricingPayload['current_price'],
                'base_price_usd' => $productPricingPayload['base_price_usd'],
                'sync_enabled' => $productPricingPayload['sync_enabled'],
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
                $this->updateProductVariants($product->fresh('subCategory'), $data['variants']);
            } elseif ($product->sync_enabled && $product->subCategory?->price_depends_on_attributes) {
                $this->refreshExistingVariantBasePrices($product->fresh('subCategory'));
            } elseif (!$product->sync_enabled && $product->subCategory?->price_depends_on_attributes) {
                $this->clearExistingVariantBasePrices($product);
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
        $hasDirectVariantPrice = (bool) ($product->subCategory?->price_depends_on_attributes ?? false);

        foreach ($variants as $variantData) {
            $sku = $variantData['sku'] ?? $this->generateSku($product);
            $stockQuantity = $variantData['stock_quantity'] ?? 0;
            $isActive = $variantData['is_active'] ?? true;
            $pricingPayload = $this->dynamicPricingService->prepareVariantPricingPayload(
                $variantData,
                (bool) $product->sync_enabled,
                $hasDirectVariantPrice
            );

            $variant = $product->variants()->create([
                'sku' => $sku,
                'price' => $pricingPayload['price'],
                'base_price_usd' => $pricingPayload['base_price_usd'],
                'stock_quantity' => $stockQuantity,
                'is_active' => $isActive,
            ]);

            // Store variant attributes (Color: Red, Size: XL, etc.)
            foreach ($variantData['attributes'] ?? [] as $attribute) {
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
        $hasDirectVariantPrice = (bool) ($product->subCategory?->price_depends_on_attributes ?? false);

        foreach ($variants as $variantData) {
            if (isset($variantData['id'])) {
                // Update existing variant
                $variant = ProductVariant::find($variantData['id']);
                if ($variant && $variant->product_id === $product->id) {
                    $sku = $variantData['sku'] ?? $variant->sku;
                    $stockQuantity = $variantData['stock_quantity'] ?? $variant->stock_quantity;
                    $isActive = $variantData['is_active'] ?? $variant->is_active;
                    $pricingPayload = $this->dynamicPricingService->prepareVariantPricingPayload(
                        $variantData,
                        (bool) $product->sync_enabled,
                        $hasDirectVariantPrice,
                        $variant
                    );

                    $variant->update([
                        'sku' => $sku,
                        'price' => $pricingPayload['price'],
                        'base_price_usd' => $pricingPayload['base_price_usd'],
                        'stock_quantity' => $stockQuantity,
                        'is_active' => $isActive,
                    ]);

                    // Update attributes - delete old and create new
                    $variant->attributes()->delete();
                    foreach ($variantData['attributes'] ?? [] as $attribute) {
                        $variant->attributes()->create([
                            'attribute_id' => $attribute['attribute_id'],
                            'attribute_value_id' => $attribute['attribute_value_id'],
                        ]);
                    }
                }
            } else {
                // Create new variant
                $sku = $variantData['sku'] ?? $this->generateSku($product);
                $stockQuantity = $variantData['stock_quantity'] ?? 0;
                $isActive = $variantData['is_active'] ?? true;
                $pricingPayload = $this->dynamicPricingService->prepareVariantPricingPayload(
                    $variantData,
                    (bool) $product->sync_enabled,
                    $hasDirectVariantPrice
                );

                $variant = $product->variants()->create([
                    'sku' => $sku,
                    'price' => $pricingPayload['price'],
                    'base_price_usd' => $pricingPayload['base_price_usd'],
                    'stock_quantity' => $stockQuantity,
                    'is_active' => $isActive,
                ]);

                foreach ($variantData['attributes'] ?? [] as $attribute) {
                    $variant->attributes()->create([
                        'attribute_id' => $attribute['attribute_id'],
                        'attribute_value_id' => $attribute['attribute_value_id'],
                    ]);
                }
            }
        }
    }

    protected function refreshExistingVariantBasePrices(Product $product): void
    {
        foreach ($product->variants as $variant) {
            if ($variant->price === null) {
                continue;
            }

            $variant->update([
                'base_price_usd' => $this->dynamicPricingService->calculateBasePriceUsd((float) $variant->price),
            ]);
        }
    }

    protected function clearExistingVariantBasePrices(Product $product): void
    {
        $product->variants()->update([
            'base_price_usd' => null,
        ]);
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
     * يراعي إعداد quantity_depends_on_attributes في الفئة الفرعية
     *
     * @param int $variantId
     * @param int $quantity
     * @return bool
     * @throws \Exception
     */
    public function decreaseVariantStock(int $variantId, int $quantity): bool
    {
        $variant = ProductVariant::with('product.subCategory')->lockForUpdate()->find($variantId);

        if (!$variant) {
            $this->throwExceptionJson('المتغير غير موجود', 404);
        }

        $product = $variant->product;
        $quantityDependsOnAttributes = $product?->subCategory?->quantity_depends_on_attributes ?? false;

        if ($quantityDependsOnAttributes) {
            // خصم من variant
            if ($variant->stock_quantity < $quantity) {
                $this->throwExceptionJson('الكمية المطلوبة غير متوفرة في المخزون', 400);
            }
            $variant->decrement('stock_quantity', $quantity);
        } else {
            // خصم من product (variants للسعر فقط)
            if (($product->quantity ?? 0) < $quantity) {
                $this->throwExceptionJson('الكمية المطلوبة غير متوفرة في المخزون', 400);
            }
            $product->decrement('quantity', $quantity);
        }

        return true;
    }

    /**
     * Increase stock quantity for a specific variant (e.g., order cancellation)
     * يراعي إعداد quantity_depends_on_attributes في الفئة الفرعية
     *
     * @param int $variantId
     * @param int $quantity
     * @return bool
     */
    public function increaseVariantStock(int $variantId, int $quantity): bool
    {
        $variant = ProductVariant::with('product.subCategory')->find($variantId);

        if (!$variant) {
            $this->throwExceptionJson('المتغير غير موجود', 404);
        }

        $product = $variant->product;
        $quantityDependsOnAttributes = $product?->subCategory?->quantity_depends_on_attributes ?? false;

        if ($quantityDependsOnAttributes) {
            // استعادة للـ variant
            $variant->increment('stock_quantity', $quantity);
        } else {
            // استعادة للـ product (variants للسعر فقط)
            $product->increment('quantity', $quantity);
        }

        return true;
    }

    /**
     * Check if variant has enough stock
     * يراعي إعداد quantity_depends_on_attributes في الفئة الفرعية
     *
     * @param int $variantId
     * @param int $quantity
     * @return bool
     */
    public function checkVariantStock(int $variantId, int $quantity): bool
    {
        $variant = ProductVariant::with('product.subCategory')->find($variantId);

        if (!$variant) {
            return false;
        }

        if (!$variant->is_active) {
            return false;
        }

        $product = $variant->product;
        $quantityDependsOnAttributes = $product?->subCategory?->quantity_depends_on_attributes ?? false;

        if ($quantityDependsOnAttributes) {
            // تحقق من variant
            return $variant->stock_quantity >= $quantity;
        } else {
            // تحقق من product (variants للسعر فقط)
            return ($product->quantity ?? 0) >= $quantity;
        }
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
            'subCategory:id,name,category_id,quantity_depends_on_attributes',
            'subCategory.category:id,name',
            'images',
            'variants' => function ($query) {
                $query->where('is_active', true);
            },
            'variants.attributes.attribute',
            'variants.attributes.value',
            'variants.product.subCategory:id,quantity_depends_on_attributes', // لحساب is_in_stock
            'ratings'
        ])->find($productId);
    }
}

